<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
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
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Domain\Repository\MediaRepository;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class MediaRepositoryTest extends DatabaseTestCase
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
        $audioConversion = $this->createAudioConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($audioConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $imageConversions = $this->mediaRepository()->findImageConversionsByMediaId($media->id);
        $videoConversions = $this->mediaRepository()->findVideoConversionsByMediaId($media->id);
        $audioConversions = $this->mediaRepository()->findAudioConversionsByMediaId($media->id);
        $restoredMultipartUpload = $this->mediaRepository()->findMultipartUploadByMediaId($media->id);

        self::assertInstanceOf(MediaImageConversionCollection::class, $imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $videoConversions);
        self::assertInstanceOf(MediaAudioConversionCollection::class, $audioConversions);
        self::assertCount(1, $imageConversions);
        self::assertCount(1, $videoConversions);
        self::assertCount(1, $audioConversions);
        self::assertInstanceOf(MediaAudioConversion::class, $audioConversions->first());
        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
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

        $this->cleanOrmHeap();

        $restoredImageConversion = $this->mediaRepository()->findImageConversionsByMediaId($mediaId)->first();

        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->cleanOrmHeap();

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
        $this->cleanOrmHeap();

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

        $this->cleanOrmHeap();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        $restoredMedia->markReady();
        $this->entityManager()->persist($restoredMedia);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $savedMedia = $this->mediaRepository()->findById($mediaId);
        $savedImageConversion = $this->mediaRepository()->findImageConversionsByMediaId($mediaId)->first();

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

        $this->cleanOrmHeap();

        $restoredImageConversion = $this->mediaRepository()->findImageConversionsByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->entityManager()->persist($restoredImageConversion);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $savedImageConversion = $this->mediaRepository()->findImageConversionsByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertInstanceOf(Media::class, $savedImageConversion->media);
        self::assertTrue($mediaId->equals($savedImageConversion->media->id));
    }

    public function testExistsReadyForMediaIdReportsReadyConversionPresence(): void
    {
        $mediaWithConversions = $this->createMedia();
        $this->entityManager()->persist($mediaWithConversions);
        $this->entityManager()->persist($this->createImageConversion($mediaWithConversions));
        $this->entityManager()->persist($this->createVideoConversion($mediaWithConversions));
        $this->entityManager()->persist($this->createAudioConversion($mediaWithConversions));
        $this->entityManager()->run();

        $mediaWithoutConversions = $this->createMedia();
        $this->entityManager()->persist($mediaWithoutConversions);
        $this->entityManager()->run();

        self::assertTrue($this->mediaRepository()->hasReadyImageConversion($mediaWithConversions->id));
        self::assertTrue($this->mediaRepository()->hasReadyVideoConversion($mediaWithConversions->id));
        self::assertTrue($this->mediaRepository()->hasReadyAudioConversion($mediaWithConversions->id));

        self::assertFalse($this->mediaRepository()->hasReadyImageConversion($mediaWithoutConversions->id));
        self::assertFalse($this->mediaRepository()->hasReadyVideoConversion($mediaWithoutConversions->id));
        self::assertFalse($this->mediaRepository()->hasReadyAudioConversion($mediaWithoutConversions->id));
    }

    public function testExistsReadyForMediaIdIgnoresNonReadyConversions(): void
    {
        // Есть только не-Ready конверсии (processing/processingFailed) -> готовой нет, метод даёт false.
        $media = $this->createMedia();
        $this->entityManager()->persist($media);
        $this->entityManager()->persist(
            $this->createImageConversion(media: $media, status: MediaConversionStatus::Processing),
        );
        $this->entityManager()->persist(
            $this->createVideoConversion(media: $media, status: MediaConversionStatus::ProcessingFailed),
        );
        $this->entityManager()->persist(
            $this->createAudioConversion(media: $media, status: MediaConversionStatus::Processing),
        );
        $this->entityManager()->run();

        self::assertFalse($this->mediaRepository()->hasReadyImageConversion($media->id));
        self::assertFalse($this->mediaRepository()->hasReadyVideoConversion($media->id));
        self::assertFalse($this->mediaRepository()->hasReadyAudioConversion($media->id));
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

    public function testFindByIdsWithConversionsReturnsEmptyForEmptyInput(): void
    {
        self::assertCount(0, $this->mediaRepository()->findByIdsWithConversions());
    }

    public function testFindByIdsWithConversionsLoadsMediaWithConversions(): void
    {
        $first = $this->createMedia();
        $second = $this->createMedia();
        $this->entityManager()->persist($first);
        $this->entityManager()->persist($second);
        $this->entityManager()->persist($this->createImageConversion($first));
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $media = $this->mediaRepository()->findByIdsWithConversions($first->id, $second->id);

        self::assertCount(2, $media);
        $restoredFirst = $media->first(static fn(Media $candidate): bool => $candidate->id->equals($first->id));
        self::assertInstanceOf(Media::class, $restoredFirst);
        self::assertCount(1, $restoredFirst->imageConversions);
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
        $audioConversion = $this->createAudioConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($audioConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $this->entityManager()->delete($media);
        $this->entityManager()->run();

        self::assertCount(0, $this->mediaRepository()->findImageConversionsByMediaId($media->id));
        self::assertCount(0, $this->mediaRepository()->findVideoConversionsByMediaId($media->id));
        self::assertCount(0, $this->mediaRepository()->findAudioConversionsByMediaId($media->id));
        self::assertNull($this->mediaRepository()->findMultipartUploadByMediaId($media->id));
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

    private function createImageConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaImageConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: $status,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('images/%s/%s/thumbnail.jpg', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(512),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    private function createVideoConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaVideoConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: $status,
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

    private function createAudioConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaAudioConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: $status,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('audios/%s/%s/normalizedAacM4a.m4a', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
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

    private function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }
}
