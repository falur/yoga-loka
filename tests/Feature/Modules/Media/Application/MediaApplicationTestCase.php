<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
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

    protected function conversionSpec(
        MediaImageConversionType $type = MediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaConversionSpec {
        return new MediaConversionSpec(type: $type, width: $width, height: $height);
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
}
