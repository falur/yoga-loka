<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Tests\TestCase;

final class MediaRepositoryTest extends TestCase
{
    public function testStoresAndRestoresMediaWithValueObjects(): void
    {
        $media = $this->createMedia();

        $this->entityManager()->persist($media);
        $this->entityManager()->run();

        $restoredMedia = $this->mediaRepository()->findById($media->id);

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertTrue($media->id->equals($restoredMedia->id));
        self::assertTrue($media->storageKey->equals($restoredMedia->storageKey));
        self::assertSame(MediaStatus::WaitingUpload, $restoredMedia->status);
        self::assertSame(MediaStorage::Upload, $restoredMedia->storage);
        self::assertTrue($media->uploadedById->equals($restoredMedia->uploadedById));
        self::assertInstanceOf(Media::class, $this->mediaRepository()->findByStorageKey($media->storageKey));
    }

    public function testStoresAndRestoresConversionsAndMultipartUpload(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $imageConversions = $this->imageConversionRepository()->findByMediaId($media->id);
        $videoConversions = $this->videoConversionRepository()->findByMediaId($media->id);
        $restoredMultipartUpload = $this->multipartUploadRepository()->findByMediaId($media->id);

        self::assertInstanceOf(MediaImageConversionCollection::class, $imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $videoConversions);
        self::assertCount(1, $imageConversions);
        self::assertCount(1, $videoConversions);
        self::assertInstanceOf(MediaMultipartUpload::class, $restoredMultipartUpload);
        self::assertInstanceOf(MediaMultipartPartCollection::class, $restoredMultipartUpload->parts);
        self::assertSame(1, $restoredMultipartUpload->parts->first()->partNumber->value());
        self::assertSame('first', $restoredMultipartUpload->parts->first()->eTag->value());
    }

    public function testLazyGhostMapperRestoresRelationsAndKeepsThemAfterSave(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmState();

        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();

        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->cleanOrmState();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertTrue($mediaId->equals($restoredMedia->id));
        self::assertInstanceOf(MediaImageConversionCollection::class, $restoredMedia->imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $restoredMedia->videoConversions);
        self::assertCount(1, $restoredMedia->imageConversions);
        self::assertCount(1, $restoredMedia->videoConversions);

        $restoredMedia->markReady();
        $this->entityManager()->persist($restoredMedia);
        $this->entityManager()->run();
        $this->cleanOrmState();

        $savedMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $savedMedia);
        self::assertSame(MediaStatus::Ready, $savedMedia->status);
        self::assertCount(1, $savedMedia->imageConversions);
        self::assertCount(1, $savedMedia->videoConversions);
    }

    public function testLazyGhostMapperSavesMediaWithoutReadingRelations(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmState();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        $restoredMedia->markReady();
        $this->entityManager()->persist($restoredMedia);
        $this->entityManager()->run();
        $this->cleanOrmState();

        $savedMedia = $this->mediaRepository()->findById($mediaId);
        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();

        self::assertInstanceOf(Media::class, $savedMedia);
        self::assertSame(MediaStatus::Ready, $savedMedia->status);
        self::assertInstanceOf(MediaImageConversionCollection::class, $savedMedia->imageConversions);
        self::assertCount(1, $savedMedia->imageConversions);
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertTrue($mediaId->equals($savedImageConversion->mediaId));
    }

    public function testLazyGhostMapperKeepsBelongsToRelationAfterReadingAndSavingConversion(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmState();

        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->entityManager()->persist($restoredImageConversion);
        $this->entityManager()->run();
        $this->cleanOrmState();

        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertInstanceOf(Media::class, $savedImageConversion->media);
        self::assertTrue($mediaId->equals($savedImageConversion->media->id));
    }

    public function testFindExpiredReturnsTypedCollection(): void
    {
        $media = $this->createMedia(
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('-1 hour')),
        );

        $this->entityManager()->persist($media);
        $this->entityManager()->run();

        $expiredMedia = $this->mediaRepository()->findExpired(new \DateTimeImmutable());

        self::assertTrue($expiredMedia->contains(static fn(Media $expired) => $expired->id->equals($media->id)));
    }

    public function testStorageKeyIsUnique(): void
    {
        $storageKey = MediaStorageKey::generate();

        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));
        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCascadeDeletesRelatedRows(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $this->entityManager()->delete($media);
        $this->entityManager()->run();

        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
        self::assertNull($this->multipartUploadRepository()->findByMediaId($media->id));
    }

    private function createMedia(
        MediaStorageKey|null $storageKey = null,
        MediaExpiration|null $expiration = null,
    ): Media {
        $storageKey ??= MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: $expiration ?? MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    private function createImageConversion(Media $media): MediaImageConversion
    {
        $storageKey = MediaStorageKey::generate();

        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('images/%s/%s/thumbnail.jpg', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(512),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    private function createVideoConversion(Media $media): MediaVideoConversion
    {
        $storageKey = MediaStorageKey::generate();

        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('videos/%s/%s/normalized.mp4', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(2048),
            width: MediaPixelDimension::fromInt(1920),
            height: MediaPixelDimension::fromInt(1080),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(5000),
        );
    }

    private function createMultipartUpload(Media $media): MediaMultipartUpload
    {
        $multipartUpload = MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: MediaFileSize::fromInt(5_242_880),
        );
        $multipartUpload->replaceParts(new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('first'),
            ),
        ]));

        return $multipartUpload;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function cleanOrmState(): void
    {
        $this->entityManager()->clean();
        $this->getContainer()->get(ORMInterface::class)->getHeap()->clean();
    }

    private function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

    private function imageConversionRepository(): MediaImageConversionRepository
    {
        return $this->getContainer()->get(MediaImageConversionRepository::class);
    }

    private function videoConversionRepository(): MediaVideoConversionRepository
    {
        return $this->getContainer()->get(MediaVideoConversionRepository::class);
    }

    private function multipartUploadRepository(): MediaMultipartUploadRepository
    {
        return $this->getContainer()->get(MediaMultipartUploadRepository::class);
    }
}
