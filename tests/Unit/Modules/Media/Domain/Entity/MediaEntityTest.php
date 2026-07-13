<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
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

    public function testRecordProcessingErrorIsNoOpOnReadyMedia(): void
    {
        // Готовое медиа не «ломается» задним числом: фиксация ошибки (транзиентной и постоянной)
        // на уже ready-медиа — no-op, статус и пустой processingError сохраняются.
        $media = $this->createMedia();
        $media->markUploaded();
        $media->markReadyMovedTo(MediaStorage::Public, $this->readyPath());

        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка'));
        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertTrue($media->processingError->isEmpty());

        $media->recordPermanentProcessingError(MediaProcessingError::fromString('Постоянная ошибка'));
        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertTrue($media->processingError->isEmpty());
    }

    public function testIsFinalizedIsTrueForReadyAndRemovedOriginal(): void
    {
        self::assertTrue($this->readyMedia()->isFinalized());
        self::assertTrue($this->readyOriginalRemovedMedia()->isFinalized());
    }

    public function testIsFinalizedIsFalseForEveryNonFinalizedStatus(): void
    {
        $error = MediaProcessingError::fromString('Ошибка обработки');

        $waitingUpload = $this->createMedia();

        $completing = $this->createMedia();
        $completing->startCompletingMultipartUpload();

        $multipartFailedCanRetry = $this->createMedia();
        $multipartFailedCanRetry->markMultipartCompletionFailedCanRetry($error);

        $multipartFailedNeedReupload = $this->createMedia();
        $multipartFailedNeedReupload->markMultipartCompletionFailedNeedReupload($error);

        $uploaded = $this->createMedia();
        $uploaded->markUploaded();

        $processing = $this->createMedia();
        $processing->markUploaded();
        $processing->startProcessing();

        $processingFailed = $this->createMedia();
        $processingFailed->markUploaded();
        $processingFailed->recordPermanentProcessingError($error);

        $nonFinalized = [
            MediaStatus::WaitingUpload->value => $waitingUpload,
            MediaStatus::CompletingMultipartUpload->value => $completing,
            MediaStatus::MultipartCompletionFailedCanRetry->value => $multipartFailedCanRetry,
            MediaStatus::MultipartCompletionFailedNeedReupload->value => $multipartFailedNeedReupload,
            MediaStatus::Uploaded->value => $uploaded,
            MediaStatus::Processing->value => $processing,
            MediaStatus::ProcessingFailed->value => $processingFailed,
        ];

        foreach ($nonFinalized as $statusValue => $media) {
            self::assertSame($statusValue, $media->status->value);
            self::assertFalse($media->isFinalized(), $statusValue);
        }
    }

    public function testIsOriginalRemovedIsTrueOnlyAfterOriginalRemoved(): void
    {
        self::assertFalse($this->createMedia()->isOriginalRemoved());
        self::assertFalse($this->readyMedia()->isOriginalRemoved());
        self::assertTrue($this->readyOriginalRemovedMedia()->isOriginalRemoved());
    }

    public function testRecordProcessingErrorIsNoOpOnReadyOriginalRemovedMedia(): void
    {
        // Симметрично кейсу для ready: после удаления оригинала медиа также не «ломается» задним
        // числом — поздняя фиксация ошибки (временной и постоянной) остаётся no-op.
        $media = $this->readyOriginalRemovedMedia();

        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка'));
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertTrue($media->processingError->isEmpty());

        $media->recordPermanentProcessingError(MediaProcessingError::fromString('Постоянная ошибка'));
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertTrue($media->processingError->isEmpty());
    }

    public function testRecordPermanentProcessingErrorMovesUploadedToProcessingFailed(): void
    {
        $media = $this->createMedia();
        $media->markUploaded();

        $media->recordPermanentProcessingError(MediaProcessingError::fromString('Битый файл'));

        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
        self::assertSame(1, $media->processingAttempts->value());
        self::assertSame('Битый файл', $media->processingError->value());
    }

    public function testCanMakeTemporaryUploadPermanent(): void
    {
        $media = $this->createMedia();

        self::assertTrue($media->expiration->isTemporary());

        $media->makePermanent();

        self::assertTrue($media->expiration->isPermanent());
    }

    public function testMarkReadyMovedToReassignsStorageAndPathAndIsIdempotent(): void
    {
        $media = $this->createMedia();
        $media->markUploaded();
        self::assertFalse($media->isReady());

        $targetPath = $this->readyPath();
        $media->markReadyMovedTo(MediaStorage::Public, $targetPath);

        self::assertTrue($media->isReady());
        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertSame(MediaStorage::Public, $media->storage);
        self::assertTrue($targetPath->equals($media->path));

        // Повторная доставка на ready — no-op: хранилище и путь не переписываются.
        $media->markReadyMovedTo(MediaStorage::Private, $this->readyPath());
        self::assertSame(MediaStorage::Public, $media->storage);
        self::assertTrue($targetPath->equals($media->path));
    }

    public function testMarkReadyMovedToAllowsRetryFromProcessingFailed(): void
    {
        $media = $this->createMedia();
        $media->markUploaded();
        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка'));
        self::assertSame(MediaStatus::ProcessingFailed, $media->status);

        $media->markReadyMovedTo(MediaStorage::Public, $this->readyPath());

        self::assertTrue($media->isReady());
    }

    public function testMarkReadyMovedToRejectsTransitionFromWaitingUpload(): void
    {
        $media = $this->createMedia();

        $this->expectException(InvalidDomainValueException::class);

        $media->markReadyMovedTo(MediaStorage::Public, $this->readyPath());
    }

    public function testMarkReadyOriginalRemovedRejectsTransitionFromNonReady(): void
    {
        $media = $this->createMedia();
        $media->markUploaded();

        $this->expectException(InvalidDomainValueException::class);

        $media->markReadyOriginalRemoved();
    }

    public function testMarkReadyOriginalRemovedIsIdempotent(): void
    {
        $media = $this->readyOriginalRemovedMedia();

        // Повторный вызов на уже removed-original — no-op, статус сохраняется.
        $media->markReadyOriginalRemoved();

        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
    }

    public function testAudioConversionCreateInitializesFields(): void
    {
        $media = $this->createMedia();

        $audioConversion = MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
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
        );

        self::assertSame(MediaAudioConversionType::NormalizedAacM4a, $audioConversion->type);
        self::assertSame(MediaConversionStatus::Ready, $audioConversion->status);
        self::assertSame($media, $audioConversion->media);
        self::assertTrue($media->id->equals($audioConversion->mediaId));
        self::assertSame(44_100, $audioConversion->sampleRate->value());
        self::assertSame([0, 64, 128, 255], $audioConversion->waveform->peaks());
        self::assertSame($audioConversion->createdAt, $audioConversion->updatedAt);
    }

    private function readyMedia(): Media
    {
        $media = $this->createMedia();
        $media->markUploaded();
        $media->markReadyMovedTo(MediaStorage::Public, $this->readyPath());

        return $media;
    }

    private function readyOriginalRemovedMedia(): Media
    {
        $media = $this->readyMedia();
        $media->markReadyOriginalRemoved();

        return $media;
    }

    private function readyPath(): MediaPath
    {
        return MediaPath::originalReady(
            storageKey: MediaStorageKey::generate(),
            type: MediaType::Image,
            extension: 'jpg',
        );
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
