<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\CompleteMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Result\MediaResult;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Public\Dto\MediaImageConversionSpecDto;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaAudioConversionProfileRequiredException;
use App\Modules\Media\Domain\Exception\MediaConversionBitrateOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionDimensionsOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionDuplicateTypeException;
use App\Modules\Media\Domain\Exception\MediaConversionPlanTypeMismatchException;
use App\Modules\Media\Domain\Exception\MediaConversionSampleRateOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionWaveformPeaksOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaMultipartUploadNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Exception\MediaUploadNotPendingException;
use App\Modules\Media\Domain\Exception\MediaUploadedObjectMismatchException;
use App\Modules\Media\Domain\Exception\MediaVideoConversionProfileRequiredException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

final readonly class CompleteMediaUploadHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private OutboxEventStoreContract $outboxEventStore,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteMediaUploadCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new MediaNotFoundException();

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new MediaAccessDeniedException();
        }

        if ($media->status !== MediaStatus::WaitingUpload) {
            throw new MediaUploadNotPendingException();
        }

        $this->assertPlanValid(plan: $command->plan, type: $media->type);

        $multipartUpload = $command->parts !== null
            ? $this->completeMultipartUpload(media: $media, parts: $command->parts)
            : null;

        $this->assertObjectUploaded($media);

        $media->markUploaded();
        $this->outboxEventStore->add(new MediaUploadedEvent(
            mediaId: $media->id->value(),
            plan: $command->plan,
        ));

        if ($multipartUpload !== null) {
            $this->mediaRepository->saveWithMultipartUpload(media: $media, multipartUpload: $multipartUpload);
        } else {
            $this->mediaRepository->save($media);
        }

        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'imageConversions' => \count($command->plan->image),
            'videoConversions' => \count($command->plan->video),
            'audioConversions' => \count($command->plan->audio),
        ]);

        return MediaResult::fromEntity($media);
    }

    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): MediaMultipartUpload
    {
        $multipartUpload = $this->mediaRepository->findMultipartUploadByMediaId($media->id)
            ?? throw new MediaMultipartUploadNotFoundException();

        $multipartUpload->replaceParts($parts);
        $this->mediaFileService->completeMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
            parts: $parts,
        );

        return $multipartUpload;
    }

    private function assertObjectUploaded(Media $media): void
    {
        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);

        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
            throw new MediaUploadedObjectMismatchException();
        }
    }

    /**
     * Валидирует ТОЛЬКО список, относящийся к типу медиа; список «не своего» типа должен быть
     * пустым (кросс-тип → 422). Диапазоны проверяются через контракт VO ::supports() на
     * Application-границе, чтобы невалидная спека не прошла подтверждение и не упала асинхронно в
     * ProcessMedia (после S3-записей и unique-конфликта).
     */
    private function assertPlanValid(MediaConversionPlanDto $plan, MediaType $type): void
    {
        match ($type) {
            MediaType::Image => $this->assertImagePlan($plan),
            MediaType::Video => $this->assertVideoPlan($plan),
            MediaType::Audio => $this->assertAudioPlan($plan),
            MediaType::Document => $this->assertDocumentPlan($plan),
        };
    }

    private function assertDocumentPlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->image !== [] || $plan->video !== [] || $plan->audio !== []) {
            throw new MediaConversionPlanTypeMismatchException();
        }
    }

    private function assertImagePlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->video !== [] || $plan->audio !== []) {
            throw new MediaConversionPlanTypeMismatchException();
        }

        $types = Collection::make($plan->image)->map(
            static fn(MediaImageConversionSpecDto $spec): string => $spec->type->value,
        );
        if ($types->count() !== $types->unique()->count()) {
            throw new MediaConversionDuplicateTypeException();
        }

        foreach ($plan->image as $spec) {
            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
                throw new MediaConversionDimensionsOutOfRangeException();
            }
        }
    }

    private function assertVideoPlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->image !== [] || $plan->audio !== []) {
            throw new MediaConversionPlanTypeMismatchException();
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->video) !== 1) {
            throw new MediaVideoConversionProfileRequiredException();
        }

        $spec = $plan->video[0];
        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
            throw new MediaConversionDimensionsOutOfRangeException();
        }

        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
            throw new MediaConversionBitrateOutOfRangeException();
        }
    }

    private function assertAudioPlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->image !== [] || $plan->video !== []) {
            throw new MediaConversionPlanTypeMismatchException();
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->audio) !== 1) {
            throw new MediaAudioConversionProfileRequiredException();
        }

        $spec = $plan->audio[0];
        if (!MediaBitrate::supports($spec->bitrate)) {
            throw new MediaConversionBitrateOutOfRangeException();
        }

        if (!MediaSampleRate::supports($spec->sampleRate)) {
            throw new MediaConversionSampleRateOutOfRangeException();
        }

        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
            throw new MediaConversionWaveformPeaksOutOfRangeException();
        }
    }
}
