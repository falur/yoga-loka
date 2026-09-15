<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\CompleteMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Public\Dto\MediaImageConversionSpecDto;
use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
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
        private IntegrationEventStoreContract $integrationEventStore,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteMediaUploadCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if ($media->status !== MediaStatus::WaitingUpload) {
            throw new ValidationException('app.media.upload_not_pending');
        }

        $this->assertPlanValid(plan: $command->plan, type: $media->type);

        if ($command->parts !== null) {
            $this->completeMultipartUpload(media: $media, parts: $command->parts);
        }

        $this->assertObjectUploaded($media);

        $media->markUploaded();
        $this->integrationEventStore->add(new MediaUploadedEvent(
            mediaId: $media->id->value(),
            plan: $command->plan,
        ));
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'imageConversions' => \count($command->plan->image),
            'videoConversions' => \count($command->plan->video),
            'audioConversions' => \count($command->plan->audio),
        ]);

        return MediaResult::fromEntity($media);
    }

    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
            ?? throw new ValidationException('app.media.multipart_upload_not_found');

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
            throw new ValidationException('app.media.uploaded_object_mismatch');
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
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }
    }

    private function assertImagePlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->video !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        $types = Collection::make($plan->image)->map(
            static fn(MediaImageConversionSpecDto $spec): string => $spec->type->value,
        );
        if ($types->count() !== $types->unique()->count()) {
            throw new ValidationException('app.media.conversion_duplicate_type');
        }

        foreach ($plan->image as $spec) {
            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
                throw new ValidationException('app.media.conversion_dimensions_out_of_range');
            }
        }
    }

    private function assertVideoPlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->image !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->video) !== 1) {
            throw new ValidationException('app.media.video_conversion_profile_required');
        }

        $spec = $plan->video[0];
        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
        }

        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }
    }

    private function assertAudioPlan(MediaConversionPlanDto $plan): void
    {
        if ($plan->image !== [] || $plan->video !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->audio) !== 1) {
            throw new ValidationException('app.media.audio_conversion_profile_required');
        }

        $spec = $plan->audio[0];
        if (!MediaBitrate::supports($spec->bitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }

        if (!MediaSampleRate::supports($spec->sampleRate)) {
            throw new ValidationException('app.media.conversion_sample_rate_out_of_range');
        }

        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
            throw new ValidationException('app.media.conversion_waveform_peaks_out_of_range');
        }
    }
}
