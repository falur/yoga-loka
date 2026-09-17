<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
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
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new MediaNotFoundException();

        if ($media->isFinalized()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: медиа уже финализировано.', context: [
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

        $imageConversions = [];
        $videoConversions = [];
        $audioConversions = [];

        match ($media->type) {
            MediaType::Image => $imageConversions = $this->buildImageConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                extension: $extension,
            ),
            MediaType::Video => $this->buildVideoConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                videoConversions: $videoConversions,
                posterConversions: $imageConversions,
            ),
            MediaType::Audio => $audioConversions = $this->buildAudioConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Document => null,
        };

        $this->persistReady(
            media: $media,
            imageConversions: $imageConversions,
            videoConversions: $videoConversions,
            audioConversions: $audioConversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );
    }

    /**
     * @param list<MediaImageConversion> $imageConversions
     * @param list<MediaVideoConversion> $videoConversions
     * @param list<MediaAudioConversion> $audioConversions
     */
    private function persistReady(
        Media $media,
        array $imageConversions,
        array $videoConversions,
        array $audioConversions,
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

        $imageConversionCollection = new MediaImageConversionCollection($imageConversions);
        $videoConversionCollection = new MediaVideoConversionCollection($videoConversions);
        $audioConversionCollection = new MediaAudioConversionCollection($audioConversions);

        $this->mediaRepository->saveWithConversions(
            media: $media,
            imageConversions: $imageConversionCollection,
            videoConversions: $videoConversionCollection,
            audioConversions: $audioConversionCollection,
        );

        $this->logger->debug(message: 'Медиа готово.', context: [
            'mediaId' => $media->id->value(),
            'targetStorage' => $targetStorage->value,
            'imageConversions' => $imageConversionCollection->count(),
            'videoConversions' => $videoConversionCollection->count(),
            'audioConversions' => $audioConversionCollection->count(),
        ]);
    }

    /**
     * @return list<MediaImageConversion>
     */
    private function buildImageConversions(
        Media $media,
        MediaConversionPlanDto $plan,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($plan->image === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($plan->image as $spec) {
            // Публичный вариант плана переводим в доменный по строковому значению: один оператор
            // без ветвления, набор вариантов держит синхронной unit-проверка совпадения.
            $type = MediaImageConversionType::from($spec->type->value);

            $conversionResult = $this->mediaImageProcessor->resize(
                originalContents: $originalContents,
                width: MediaPixelDimension::fromInt($spec->width),
                height: MediaPixelDimension::fromInt($spec->height),
                targetMimeType: $media->mimeType,
            );
            $conversionPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $type,
                extension: $extension,
            );
            $this->mediaFileService->putObject(
                storage: $targetStorage,
                path: $conversionPath,
                contents: $conversionResult->contents,
                mimeType: $conversionResult->mimeType,
            );

            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
                'type' => $type->value,
                'path' => $conversionPath->value(),
                'size' => $conversionResult->size->value(),
            ]);

            // width/height берём из результата процессора (размер реально записанного объекта),
            // а не из spec — устойчиво к смене режима ресайза. См. README.
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: $type,
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
     * Заполняет видео-конверсии и постеры (MediaImageConversion) раздельными выходными списками:
     * постер видео — это image-конверсия, а не video, поэтому смешивать их в один список нельзя —
     * каждый тип сохраняется своей типизированной коллекцией. Оба списка передаются по ссылке, а не
     * возвращаются кортежем, — тип-контракты проекта запрещают array shape и tuple.
     *
     * @param list<MediaVideoConversion> $videoConversions
     * @param list<MediaImageConversion> $posterConversions
     * @param-out list<MediaVideoConversion> $videoConversions
     * @param-out list<MediaImageConversion> $posterConversions
     */
    private function buildVideoConversions(
        Media $media,
        MediaConversionPlanDto $plan,
        MediaStorage $targetStorage,
        array &$videoConversions,
        array &$posterConversions,
    ): void {
        $videoConversions = [];
        $posterConversions = [];

        foreach ($plan->video as $spec) {
            $type = MediaVideoConversionType::from($spec->type->value);
            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $type, extension: 'mp4');
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

            $videoConversions[] = MediaVideoConversion::create(
                media: $media,
                type: $type,
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
            $posterConversions[] = MediaImageConversion::create(
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
                'type' => $type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }
    }

    /**
     * @return list<MediaAudioConversion>
     */
    private function buildAudioConversions(
        Media $media,
        MediaConversionPlanDto $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->audio as $spec) {
            $type = MediaAudioConversionType::from($spec->type->value);
            $normalizedPath = MediaPath::audioConversion(storageKey: $media->storageKey, type: $type, extension: 'm4a');

            $result = $this->mediaAudioProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
            );

            $conversions[] = MediaAudioConversion::create(
                media: $media,
                type: $type,
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
                'type' => $type->value,
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
