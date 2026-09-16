<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Result\MediaResult;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Exception\MediaOriginalNotRemovableException;
use App\Modules\Media\Domain\Exception\MediaWithoutConversionsToKeepException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Удаляет оригинальный объект медиа из целевого бакета, сохраняя конверсии. Без #[Transactional]:
 * сначала идемпотентный deleteObject оригинала (404 → no-op), затем один атомарный persist+run() с
 * переходом в readyOriginalRemoved. Идемпотентен: на уже removed-original — ранний no-op без обращения
 * к S3. На сбое после deleteObject до flush статус остаётся ready, повтор команды довыполнит переход.
 *
 * Осознанный компромисс порядка «удалить в S3 → зафиксировать статус»: пока переход не довыполнен,
 * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrls/лента/аватар) вернёт ссылку
 * на уже удалённый объект — короткое окно битой ссылки. Команда предполагает
 * повторный вызов при сбое (автоматического реиспуска, как у тяжёлой обработки через outbox, тут нет),
 * поэтому пока не подключена к прямому запуску пользователем; перед подключением к реальному триггеру компромисс
 * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
 */
final readonly class RemoveMediaOriginalHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaConversionsChecker $mediaConversionsChecker,
        private MediaFileServiceContract $mediaFileService,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(RemoveMediaOriginalCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new MediaNotFoundException();

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new MediaAccessDeniedException();
        }

        if ($media->isOriginalRemoved()) {
            $this->logger->debug(message: 'Удаление оригинала медиа пропущено: оригинал уже удалён.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return MediaResult::fromEntity($media);
        }

        if (!$media->isReady()) {
            throw new MediaOriginalNotRemovableException();
        }

        if (!$this->mediaConversionsChecker->hasAnyReadyConversion($media->id)) {
            throw new MediaWithoutConversionsToKeepException();
        }

        // Удаляем текущий оригинал в целевом бакете строго до доменного перехода. 404 идемпотентно
        // игнорируется сервисом, поэтому повтор команды после частичного сбоя безопасен.
        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);

        $media->markReadyOriginalRemoved();
        $this->mediaRepository->save($media);

        $this->logger->debug(message: 'Оригинал медиа удалён.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'storage' => $media->storage->value,
            'path' => $media->path->value(),
        ]);

        return MediaResult::fromEntity($media);
    }
}
