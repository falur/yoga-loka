<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\NotFoundException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Без #[Transactional]: все S3/Imagick-операции выполняются вне транзакции, затем один
 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
 * пересоздаёт их без конфликта по unique (media_id, type).
 */
final readonly class ProcessMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaImageProcessorContract $mediaImageProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('Медиа не найдено.');

        if ($media->isReady()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return;
        }

        $targetStorage = $this->targetStorage($media->visibility);
        $extension = $media->path->extension();

        $conversions = $this->buildConversions(
            media: $media,
            specs: $command->conversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );

        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
        $this->mediaFileService->copyObject(
            fromStorage: $media->storage,
            fromPath: $media->path,
            toStorage: $targetStorage,
            toPath: $targetPath,
        );
        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);

        $this->entityManager->persist($media);
        foreach ($conversions as $conversion) {
            $this->entityManager->persist($conversion);
        }
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа готово.', context: [
            'mediaId' => $media->id->value(),
            'targetStorage' => $targetStorage->value,
            'conversions' => \count($conversions),
        ]);
    }

    /**
     * @param list<MediaConversionSpec> $specs
     * @return list<MediaImageConversion>
     */
    private function buildConversions(
        Media $media,
        array $specs,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($specs === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($specs as $spec) {
            $conversionResult = $this->mediaImageProcessor->resize(
                originalContents: $originalContents,
                width: MediaPixelDimension::fromInt($spec->width),
                height: MediaPixelDimension::fromInt($spec->height),
                targetMimeType: $media->mimeType,
            );
            $conversionPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $spec->type,
                extension: $extension,
            );
            $this->mediaFileService->putObject(
                storage: $targetStorage,
                path: $conversionPath,
                contents: $conversionResult->contents,
                mimeType: $conversionResult->mimeType,
            );

            // width/height берём из результата процессора (conversionResult->width/height), а не из
            // spec.width/spec.height: в БД должен лежать размер реально записанного объекта. Текущий
            // ImagickMediaImageProcessor использует cover() и всегда выдаёт точные spec-размеры, поэтому
            // значения совпадают; чтение из результата устойчиво к будущей смене режима ресайза (contain/
            // scale), где фактический размер мог бы отличаться от запрошенного. См. README.
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $conversionPath,
                mimeType: $conversionResult->mimeType,
                size: $conversionResult->size,
                width: $conversionResult->width,
                height: $conversionResult->height,
            );
        }

        return $conversions;
    }

    private function targetStorage(MediaVisibility $visibility): MediaStorage
    {
        return match ($visibility) {
            MediaVisibility::Public => MediaStorage::Public,
            MediaVisibility::Private => MediaStorage::Private,
        };
    }
}
