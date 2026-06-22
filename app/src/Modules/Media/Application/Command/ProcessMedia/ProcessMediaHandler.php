<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
 * пересоздаёт их без конфликта по unique (media_id, type) (процессоры перезаписывают объекты
 * по детерминированным путям).
 */
final readonly class ProcessMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaImageProcessorContract $mediaImageProcessor,
        private MediaVideoProcessorContract $mediaVideoProcessor,
        private MediaAudioProcessorContract $mediaAudioProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if ($media->isReady()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return;
        }

        $this->logger->debug(message: 'Начата обработка медиа.', context: [
            'mediaId' => $media->id->value(),
            'type' => $media->type->value,
        ]);

        $targetStorage = $this->targetStorage($media->visibility);
        $extension = $media->path->extension();

        $conversions = match ($media->type) {
            MediaType::Image => $this->buildImageConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                extension: $extension,
            ),
            MediaType::Video => $this->buildVideoConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Audio => $this->buildAudioConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
        };

        $this->persistReady(
            media: $media,
            conversions: $conversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     */
    private function persistReady(
        Media $media,
        array $conversions,
        MediaStorage $targetStorage,
        string $extension,
    ): void {
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
            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
        ]);
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @param class-string $conversionClass
     */
    private function countByClass(array $conversions, string $conversionClass): int
    {
        return Collection::make($conversions)
            ->filter(static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
                => $conversion instanceof $conversionClass)
            ->count();
    }

    /**
     * @return list<MediaImageConversion>
     */
    private function buildImageConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($plan->image === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($plan->image as $spec) {
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

            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
                'type' => $spec->type->value,
                'path' => $conversionPath->value(),
                'size' => $conversionResult->size->value(),
            ]);

            // width/height берём из результата процессора (размер реально записанного объекта),
            // а не из spec — устойчиво к смене режима ресайза. См. README.
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

    /**
     * @return list<MediaVideoConversion|MediaImageConversion>
     */
    private function buildVideoConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->video as $spec) {
            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'mp4');
            $posterPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            );

            $result = $this->mediaVideoProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
                posterPath: $posterPath,
            );

            $conversions[] = MediaVideoConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                width: $result->width,
                height: $result->height,
                duration: $result->duration,
                bitrate: $result->bitrate,
            );
            // Постер видео — MediaImageConversion type=Poster размером транскода (width/height).
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: MediaImageConversionType::Poster,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $posterPath,
                mimeType: $result->posterMimeType,
                size: $result->posterSize,
                width: $result->width,
                height: $result->height,
            );

            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }

        return $conversions;
    }

    /**
     * @return list<MediaAudioConversion>
     */
    private function buildAudioConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->audio as $spec) {
            $normalizedPath = MediaPath::audioConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'm4a');

            $result = $this->mediaAudioProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
            );

            $conversions[] = MediaAudioConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                duration: $result->duration,
                bitrate: $result->bitrate,
                sampleRate: $result->sampleRate,
                waveform: $result->waveform,
            );

            $this->logger->debug(message: 'Создана конверсия аудио с волной.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
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
