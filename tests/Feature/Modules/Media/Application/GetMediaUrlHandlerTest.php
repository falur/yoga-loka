<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsDirectPublicUrlForPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
        ));

        // Для public TTL не применяется и VO не строится: прямой URL без срока.
        self::assertSame('http://minio/media-public/object', $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testIgnoresInvalidTtlForPublicMedia(): void
    {
        // Для public-медиа срок не применяется и VO не строится, поэтому заведомо
        // невалидный TTL (вне диапазона MediaPresignedTtl) проходит как успех без presignGet.
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 0,
        ));

        self::assertSame('http://minio/media-public/object', $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testReturnsPresignedUrlForPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);

        $capturedExpiresAt = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): string {
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/signed';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
        ));

        self::assertSame('http://minio/signed', $result->url);
        self::assertNotNull($result->expiresAt);

        // TTL presigned GET берётся из запроса (300), а не из конфиг-дефолта 900.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testReturnsUrlForRequestedConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $conversion = $this->thumbnailConversion($media);
        $this->persist($media, $conversion);

        $capturedPath = null;
        $capturedExpiresAt = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedPath, &$capturedExpiresAt): string {
                $capturedPath = $path->value();
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/signed-thumbnail';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 450,
            conversionType: MediaImageConversionType::Thumbnail,
        ));

        self::assertSame('http://minio/signed-thumbnail', $result->url);
        self::assertSame($conversion->path->value(), $capturedPath);

        // Конверсия private отдаёт presigned URL с тем же сроком из запроса (450).
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+450 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testRejectsMissingConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);

        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
            conversionType: MediaImageConversionType::Poster,
        ));
    }

    public function testRejectsMediaThatIsNotReady(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))
            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))
            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
    }

    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
    {
        return new GetMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaImageConversionRepository: $this->imageConversionRepository(),
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

    private function thumbnailConversion(Media $media): MediaImageConversion
    {
        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }
}
