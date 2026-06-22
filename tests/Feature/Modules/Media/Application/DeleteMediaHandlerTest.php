<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\DeleteMedia\DeleteMediaCommand;
use App\Modules\Media\Application\Command\DeleteMedia\DeleteMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class DeleteMediaHandlerTest extends MediaApplicationTestCase
{
    public function testDeletesWaitingUploadWithActiveMultipart(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media, $this->multipartUploadFor($media));

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('abortMultipartUpload');
        $fileService->expects(self::once())->method('deleteObject');

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertNull($this->mediaRepository()->findById($media->id));
    }

    public function testDeletesReadyMediaWithoutMultipartAbort(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('abortMultipartUpload');
        $fileService->expects(self::once())->method('deleteObject');

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertNull($this->mediaRepository()->findById($media->id));
    }

    public function testDeletesReadyMediaConversionObjectsFromStorage(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithImageConversion($userId, MediaStorage::Public);
        $conversionPath = $this->imageConversionRepository()->findByMediaId($media->id)->first()?->path;
        self::assertNotNull($conversionPath);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deletedPaths = [];
        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path->value();
            },
        );

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertContains($conversionPath->value(), $deletedPaths);
        self::assertContains($media->path->value(), $deletedPaths);
        self::assertNull($this->mediaRepository()->findById($media->id));
        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testDeletesReadyMediaVideoConversionObjectsFromStorage(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithVideoConversion($userId, MediaStorage::Public);
        $conversionPath = $this->videoConversionRepository()->findByMediaId($media->id)->first()?->path;
        self::assertNotNull($conversionPath);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deletedPaths = [];
        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path->value();
            },
        );

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertContains($conversionPath->value(), $deletedPaths);
        self::assertContains($media->path->value(), $deletedPaths);
        self::assertNull($this->mediaRepository()->findById($media->id));
        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
    }

    public function testDeletesReadyMediaAudioConversionObjectsFromStorage(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithAudioConversion($userId, MediaStorage::Public);
        $conversionPath = $this->audioConversionRepository()->findByMediaId($media->id)->first()?->path;
        self::assertNotNull($conversionPath);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deletedPaths = [];
        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path->value();
            },
        );

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertContains($conversionPath->value(), $deletedPaths);
        self::assertContains($media->path->value(), $deletedPaths);
        self::assertNull($this->mediaRepository()->findById($media->id));
        self::assertCount(0, $this->audioConversionRepository()->findByMediaId($media->id));
    }

    public function testDeletesWaitingUploadWithoutMultipartRecord(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('abortMultipartUpload');
        $fileService->expects(self::once())->method('deleteObject');

        $this->handler($fileService)->handle(new DeleteMediaCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertNull($this->mediaRepository()->findById($media->id));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new DeleteMediaCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(ForbiddenException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new DeleteMediaCommand(
            userId: UserId::generate()->value(),
            mediaId: $media->id->value(),
        ));
    }

    private function handler(MediaFileServiceContract $fileService): DeleteMediaHandler
    {
        return new DeleteMediaHandler(
            mediaRepository: $this->mediaRepository(),
            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
            mediaImageConversionRepository: $this->imageConversionRepository(),
            mediaVideoConversionRepository: $this->videoConversionRepository(),
            mediaAudioConversionRepository: $this->audioConversionRepository(),
            mediaFileService: $fileService,
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }

    private function readyMediaWithImageConversion(UserId $userId, MediaStorage $storage): Media
    {
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $media->markReadyMovedTo(
            $storage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        $this->persist(MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(128),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        ));

        return $media;
    }

    private function readyMediaWithVideoConversion(UserId $userId, MediaStorage $storage): Media
    {
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $media->markUploaded();
        $media->markReadyMovedTo(
            $storage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4'),
        );
        $this->persist($media);

        $this->persist(MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: $storage,
            path: MediaPath::fromString(
                \sprintf('videos/%s/%s/normalized.mp4', $media->storageKey->shard(), $media->storageKey),
            ),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(2048),
            width: MediaPixelDimension::fromInt(1920),
            height: MediaPixelDimension::fromInt(1080),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(800_000),
        ));

        return $media;
    }

    private function readyMediaWithAudioConversion(UserId $userId, MediaStorage $storage): Media
    {
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $media->markUploaded();
        $media->markReadyMovedTo(
            $storage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
        );
        $this->persist($media);

        $this->persist(MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: $storage,
            path: MediaPath::audioConversion(
                storageKey: $media->storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        ));

        return $media;
    }

    private function multipartUploadFor(Media $media): MediaMultipartUpload
    {
        return MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-1'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: $media->size,
        );
    }
}
