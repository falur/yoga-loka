<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class MediaEntityTest extends TestCase
{
    public function testCreateInitializesMedia(): void
    {
        $media = $this->createMedia();

        self::assertSame(MediaStatus::WaitingUpload, $media->status);
        self::assertSame(MediaType::Image, $media->type);
        self::assertSame(MediaVisibility::Private, $media->visibility);
        self::assertTrue($media->processingError->isEmpty());
        self::assertSame(0, $media->processingAttempts->value());
        self::assertNotSame('', (string) $media->id);
        self::assertSame($media->createdAt, $media->updatedAt);
    }

    public function testMultipartStatusTransitions(): void
    {
        $media = $this->createMedia();
        $processingError = MediaProcessingError::fromString('Не удалось завершить загрузку');

        $media->startCompletingMultipartUpload();
        self::assertSame(MediaStatus::CompletingMultipartUpload, $media->status);

        $media->markMultipartCompletionFailedCanRetry($processingError);
        self::assertSame(MediaStatus::MultipartCompletionFailedCanRetry, $media->status);
        self::assertTrue($processingError->equals($media->processingError));

        $media->markMultipartCompletionFailedNeedReupload($processingError);
        self::assertSame(MediaStatus::MultipartCompletionFailedNeedReupload, $media->status);
    }

    public function testProcessingStatusTransitions(): void
    {
        $media = $this->createMedia();

        $media->markUploaded();
        self::assertSame(MediaStatus::Uploaded, $media->status);

        $media->startProcessing();
        self::assertSame(MediaStatus::Processing, $media->status);

        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка обработки'));
        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
        self::assertSame(1, $media->processingAttempts->value());

        $media->startProcessing();
        $media->markReady();
        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertTrue($media->processingError->isEmpty());

        $media->markReadyOriginalRemoved();
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
    }

    public function testCanMakeTemporaryUploadPermanent(): void
    {
        $media = $this->createMedia();

        self::assertTrue($media->expiration->isTemporary());

        $media->makePermanent();

        self::assertTrue($media->expiration->isPermanent());
    }

    private function createMedia(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}
