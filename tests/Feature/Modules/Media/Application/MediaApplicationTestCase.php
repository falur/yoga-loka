<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\TestCase;

abstract class MediaApplicationTestCase extends TestCase
{
    protected function createMedia(
        UserId|null $userId = null,
        MediaVisibility $visibility = MediaVisibility::Private,
        MediaType $type = MediaType::Image,
        MediaFileSize|null $size = null,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: $type,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $extension),
            mimeType: MediaMimeType::fromString($mimeType),
            size: $size ?? MediaFileSize::fromInt(1024),
            uploadedById: $userId ?? UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->run();
    }

    protected function imageConversionSpec(
        MediaImageConversionType $type = MediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaImageConversionSpec {
        return new MediaImageConversionSpec(type: $type, width: $width, height: $height);
    }

    protected function videoConversionSpec(
        MediaVideoConversionType $type = MediaVideoConversionType::NormalizedMp4H264,
        int $width = 1280,
        int $height = 720,
        int $videoBitrate = 1_000_000,
        int $audioBitrate = 128_000,
    ): MediaVideoConversionSpec {
        return new MediaVideoConversionSpec(
            type: $type,
            width: $width,
            height: $height,
            videoBitrate: $videoBitrate,
            audioBitrate: $audioBitrate,
        );
    }

    protected function audioConversionSpec(
        MediaAudioConversionType $type = MediaAudioConversionType::NormalizedAacM4a,
        int $bitrate = 128_000,
        int $sampleRate = 44_100,
        int $waveformPeaks = 64,
    ): MediaAudioConversionSpec {
        return new MediaAudioConversionSpec(
            type: $type,
            bitrate: $bitrate,
            sampleRate: $sampleRate,
            waveformPeaks: $waveformPeaks,
        );
    }

    protected function emptyPlan(): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: []);
    }

    protected function imagePlan(MediaImageConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: \array_values($specs), video: [], audio: []);
    }

    protected function videoPlan(MediaVideoConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: \array_values($specs), audio: []);
    }

    protected function audioPlan(MediaAudioConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: \array_values($specs));
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

    protected function multipartUploadRepository(): MediaMultipartUploadRepository
    {
        return $this->getContainer()->get(MediaMultipartUploadRepository::class);
    }

    protected function imageConversionRepository(): MediaImageConversionRepository
    {
        return $this->getContainer()->get(MediaImageConversionRepository::class);
    }

    protected function videoConversionRepository(): MediaVideoConversionRepository
    {
        return $this->getContainer()->get(MediaVideoConversionRepository::class);
    }

    protected function audioConversionRepository(): MediaAudioConversionRepository
    {
        return $this->getContainer()->get(MediaAudioConversionRepository::class);
    }
}
