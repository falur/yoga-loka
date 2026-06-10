<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\CompleteMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

final readonly class CompleteMediaUploadHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaFileServiceContract $mediaFileService,
        private OutboxEventStoreContract $outboxEventStore,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteMediaUploadCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('Медиа не найдено.');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('Нет доступа к этому медиа.');
        }

        if ($media->status !== MediaStatus::WaitingUpload) {
            throw new ValidationException('Загрузка медиа не ожидает подтверждения.');
        }

        $this->assertConversionsValid($command->conversions);

        if ($command->parts !== null) {
            $this->completeMultipartUpload(media: $media, parts: $command->parts);
        }

        $this->assertObjectUploaded($media);

        $media->markUploaded();
        $this->outboxEventStore->add(new MediaUploaded(
            mediaId: $media->id->value(),
            conversions: $command->conversions,
        ));
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'conversions' => \count($command->conversions),
        ]);

        return MediaResult::fromEntity($media);
    }

    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
            ?? throw new ValidationException('Для медиа не найдена multipart-загрузка.');

        $multipartUpload->replaceParts($parts);
        $this->entityManager->persist($multipartUpload);
        $this->mediaFileService->completeMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
            parts: $parts,
        );
    }

    private function assertObjectUploaded(Media $media): void
    {
        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);

        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
            throw new ValidationException('Загруженный объект отсутствует или его размер не совпадает с заявленным.');
        }
    }

    /**
     * @param list<MediaConversionSpec> $conversions
     */
    private function assertConversionsValid(array $conversions): void
    {
        // Симметричный guard на той же Application-границе: проверяем обе границы домена
        // (MediaPixelDimension MIN=1, MAX=100_000) через контракт VO, а не магическими числами.
        // Иначе невалидная спека (например, 0 или 200000) проходит подтверждение, кладётся в outbox
        // и падает асинхронно в ProcessMedia -> терминальный ProcessingFailed.
        $hasOutOfRangeConversion = Collection::make($conversions)->contains(
            static fn(MediaConversionSpec $conversion): bool => !MediaPixelDimension::supports($conversion->width)
                || !MediaPixelDimension::supports($conversion->height),
        );

        if ($hasOutOfRangeConversion) {
            throw new ValidationException('Ширина и высота конверсии вне допустимого диапазона.');
        }
    }
}
