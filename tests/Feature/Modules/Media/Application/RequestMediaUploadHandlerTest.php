<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadCommand;
use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\MediaPresignedPart;
use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
use App\Modules\Media\Application\Dto\MediaUploadMode;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Feature\Modules\Media\Flow\Fixture\RecordingMediaLogger;

final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
{
    public function testRequestsSingleUpload(): void
    {
        $capturedExpiresAt = null;
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('presignPut')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                MediaMimeType $mimeType,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): string {
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/put-url';
            },
        );

        $logger = new RecordingMediaLogger();
        $result = $this->handler($fileService, logger: $logger)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(presignedTtl: 300),
            fileMeta: $this->fileMeta(size: 1024),
        ));

        self::assertSame(MediaUploadMode::Single, $result->uploadMode);
        self::assertSame('http://minio/put-url', $result->putUrl);
        self::assertNull($result->parts);

        // TTL presigned-ссылки берётся из спеки (300), а не из конфиг-дефолта 900.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
        self::assertSame(300, $logger->contextFor('Запрошена загрузка медиа.')['presignedTtlSeconds']);

        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
        self::assertNotNull($media);
        self::assertSame(MediaStatus::WaitingUpload, $media->status);
    }

    public function testRequestsMultipartUploadWhenSizeReachesThreshold(): void
    {
        $capturedExpiresAt = null;
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('createMultipartUpload')->willReturn(MediaMultipartUploadIdValue::fromString('upload-1'));
        $fileService->method('presignUploadParts')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                MediaMultipartUploadIdValue $uploadId,
                MediaMultipartPartsCount $partsCount,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): MediaPresignedPartCollection {
                $capturedExpiresAt = $expiresAt;

                return new MediaPresignedPartCollection([new MediaPresignedPart(partNumber: 1, url: 'http://minio/part-1')]);
            },
        );
        $fileService->expects(self::never())->method('presignPut');

        $result = $this->handler($fileService, threshold: 5_242_880, partSize: 5_242_880)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(maxSize: 5_242_880, presignedTtl: 600),
            fileMeta: $this->fileMeta(size: 5_242_880),
        ));

        self::assertSame(MediaUploadMode::Multipart, $result->uploadMode);
        self::assertSame('upload-1', $result->uploadId);
        self::assertNotNull($result->parts);
        self::assertCount(1, $result->parts);
        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));

        // TTL частей multipart тоже берётся из спеки (600), а не из конфига.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+600 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testRejectsUnsupportedMimeType(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(mimeType: 'audio/mpeg'),
        ));
    }

    public function testRejectsMimeTypeOutsideSpec(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(mimeType: 'image/png'),
        ));
    }

    public function testRejectsSizeAboveSpecMax(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(maxSize: 512),
            fileMeta: $this->fileMeta(size: 1024),
        ));
    }

    public function testRejectsFileNameWithoutExtension(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(fileName: 'avatar'),
        ));
    }

    private function handler(
        MediaFileServiceContract|null $fileService = null,
        int $threshold = 16_777_216,
        int $partSize = 8_388_608,
        LoggerInterface|null $logger = null,
    ): RequestMediaUploadHandler {
        return new RequestMediaUploadHandler(
            mediaFileService: $fileService ?? $this->createStub(MediaFileServiceContract::class),
            mediaTypeResolver: new MediaTypeResolver(),
            mediaConfig: new MediaConfig(
                stagingTtlSeconds: 86_400,
                multipartThresholdBytes: $threshold,
                multipartPartSizeBytes: $partSize,
                imageProcessingDriver: 'imagick',
                ffmpegBinaryPath: '/usr/bin/ffmpeg',
                ffprobeBinaryPath: '/usr/bin/ffprobe',
                ffmpegTimeoutSeconds: 1800,
                ffmpegThreads: 0,
            ),
            entityManager: $this->entityManager(),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function spec(int $maxSize = 1_048_576, int $presignedTtl = 300): MediaUploadSpec
    {
        return new MediaUploadSpec(
            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
            maxSize: MediaFileSize::fromInt($maxSize),
            visibility: MediaVisibility::Private,
            presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
        );
    }

    private function fileMeta(
        string $fileName = 'avatar.jpg',
        string $mimeType = 'image/jpeg',
        int $size = 1024,
    ): MediaFileMeta {
        return new MediaFileMeta(
            fileName: $fileName,
            mimeType: MediaMimeType::fromString($mimeType),
            size: MediaFileSize::fromInt($size),
        );
    }
}
