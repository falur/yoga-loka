<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Cycle;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaAudioConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaVideoConversionMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * MediaExpirationTypecast и MediaProcessingErrorTypecast (первая категория «Правила переноса
 * значений колонок») удалены волной E — их null-bridging логика (nullable-колонка <-> VO с
 * сентинелом permanent()/none()) перенесена сюда без изменения поведения: expires_at получил
 * встроенный typecast: 'datetime' Cycle (приём фазы 3/4/5), processing_error стал обычным
 * nullable-строковым полем без typecast.
 */
final class MediaMapperTest extends TestCase
{
    public function testToDomainMapsPermanentExpirationAndEmptyProcessingErrorFromNullColumns(): void
    {
        $cycleEntity = $this->cycleMediaEntity(expiresAt: null, processingError: null);

        $media = $this->mapper()->toDomain($cycleEntity);

        self::assertTrue($media->expiration->isPermanent());
        self::assertTrue($media->processingError->isEmpty());
    }

    public function testToDomainMapsTemporaryExpirationAndNonEmptyProcessingErrorFromColumns(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-05-22 15:00:00');
        $cycleEntity = $this->cycleMediaEntity(expiresAt: $expiresAt, processingError: 'Не удалось обработать файл');

        $media = $this->mapper()->toDomain($cycleEntity);

        self::assertTrue($media->expiration->isTemporary());
        self::assertSame($expiresAt, $media->expiration->value());
        self::assertFalse($media->processingError->isEmpty());
        self::assertSame('Не удалось обработать файл', $media->processingError->value());
    }

    public function testToCycleEntityUncastsPermanentExpirationAndEmptyProcessingErrorToNullColumns(): void
    {
        $media = $this->media(
            expiration: MediaExpiration::permanent(),
            processingError: MediaProcessingError::none(),
        );

        $cycleEntity = $this->mapper()->toCycleEntity($media);

        self::assertNull($cycleEntity->expiresAt);
        self::assertNull($cycleEntity->processingError);
    }

    public function testToCycleEntityUncastsTemporaryExpirationAndNonEmptyProcessingErrorToColumns(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-05-22 15:00:00');
        $media = $this->media(
            expiration: MediaExpiration::temporaryUntil($expiresAt),
            processingError: MediaProcessingError::fromString('Битый файл'),
        );

        $cycleEntity = $this->mapper()->toCycleEntity($media);

        self::assertSame($expiresAt, $cycleEntity->expiresAt);
        self::assertSame('Битый файл', $cycleEntity->processingError);
    }

    private function mapper(): MediaMapper
    {
        return new MediaMapper(
            imageConversionMapper: new MediaImageConversionMapper(),
            videoConversionMapper: new MediaVideoConversionMapper(),
            audioConversionMapper: new MediaAudioConversionMapper(),
        );
    }

    private function cycleMediaEntity(\DateTimeImmutable|null $expiresAt, string|null $processingError): CycleMediaEntity
    {
        $storageKey = MediaStorageKey::generate();

        $cycleEntity = new CycleMediaEntity();
        $cycleEntity->id = MediaId::generate()->value();
        $cycleEntity->storageKey = $storageKey->value();
        $cycleEntity->type = MediaType::Image;
        $cycleEntity->status = MediaStatus::WaitingUpload;
        $cycleEntity->visibility = MediaVisibility::Private;
        $cycleEntity->storage = MediaStorage::Upload;
        $cycleEntity->path = MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->value();
        $cycleEntity->mimeType = MediaMimeType::fromString('image/jpeg')->value();
        $cycleEntity->size = 1024;
        $cycleEntity->uploadedById = UserId::generate()->value();
        $cycleEntity->expiresAt = $expiresAt;
        $cycleEntity->processingAttempts = 0;
        $cycleEntity->processingError = $processingError;
        $cycleEntity->createdAt = new \DateTimeImmutable();
        $cycleEntity->updatedAt = new \DateTimeImmutable();

        return $cycleEntity;
    }

    private function media(MediaExpiration $expiration, MediaProcessingError $processingError): Media
    {
        $storageKey = MediaStorageKey::generate();

        $media = Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: $expiration,
        );

        if ($processingError->isEmpty()) {
            return $media;
        }

        $media->recordPermanentProcessingError($processingError);

        return $media;
    }
}
