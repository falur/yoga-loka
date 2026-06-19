<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\ValueObject\UserId;

final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsNullForMissingMedia(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullForMediaThatIsNotReady(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsDirectUrlForReadyPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertSame('http://minio/media-public/object', $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testReturnsPresignedUrlForReadyPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturn('http://minio/signed');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertSame('http://minio/signed', $result->url);
        self::assertNotNull($result->expiresAt);
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
    {
        return new FindMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaFileService: $fileService,
        );
    }

    private function readyMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }
}
