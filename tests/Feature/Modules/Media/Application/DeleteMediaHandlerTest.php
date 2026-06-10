<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\Media\DeleteMedia\DeleteMediaCommand;
use App\Modules\Media\Application\Command\Media\DeleteMedia\DeleteMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
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
