<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Infrastructure\Cycle\MediaExpirationTypecast;
use App\Modules\Media\Infrastructure\Cycle\MediaProcessingErrorTypecast;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media',
    table: 'media',
    repository: MediaRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Media
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaId::class)]
    public private(set) MediaId $id;

    #[Column(type: 'uuid', name: 'storage_key', typecast: MediaStorageKey::class)]
    public private(set) MediaStorageKey $storageKey;

    #[Column(type: 'string(32)', typecast: MediaType::class)]
    public private(set) MediaType $type;

    #[Column(type: 'string(64)', typecast: MediaStatus::class)]
    public private(set) MediaStatus $status;

    #[Column(type: 'string(16)', typecast: MediaVisibility::class)]
    public private(set) MediaVisibility $visibility;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'uuid', name: 'uploaded_by_id', typecast: UserId::class)]
    public private(set) UserId $uploadedById;

    #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: MediaExpirationTypecast::class)]
    public private(set) MediaExpiration $expiration;

    #[Column(type: 'integer', name: 'processing_attempts', typecast: MediaProcessingAttempts::class)]
    public private(set) MediaProcessingAttempts $processingAttempts;

    #[Column(type: 'text', name: 'processing_error', nullable: true, typecast: MediaProcessingErrorTypecast::class)]
    public private(set) MediaProcessingError $processingError;

    #[HasMany(
        target: MediaImageConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaImageConversionCollection::class,
    )]
    public private(set) MediaImageConversionCollection $imageConversions;

    #[HasMany(
        target: MediaVideoConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaVideoConversionCollection::class,
    )]
    public private(set) MediaVideoConversionCollection $videoConversions;

    #[HasMany(
        target: MediaAudioConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaAudioConversionCollection::class,
    )]
    public private(set) MediaAudioConversionCollection $audioConversions;

    public static function create(
        MediaStorageKey $storageKey,
        MediaType $type,
        MediaVisibility $visibility,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        UserId $uploadedById,
        MediaExpiration $expiration,
    ): self {
        $media = new self();
        $media->id = MediaId::generate();
        $media->storageKey = $storageKey;
        $media->type = $type;
        $media->status = MediaStatus::WaitingUpload;
        $media->visibility = $visibility;
        $media->storage = MediaStorage::Upload;
        $media->path = $path;
        $media->mimeType = $mimeType;
        $media->size = $size;
        $media->uploadedById = $uploadedById;
        $media->expiration = $expiration;
        $media->processingAttempts = MediaProcessingAttempts::zero();
        $media->processingError = MediaProcessingError::none();
        $media->imageConversions = new MediaImageConversionCollection();
        $media->videoConversions = new MediaVideoConversionCollection();
        $media->audioConversions = new MediaAudioConversionCollection();
        $media->initializeTimestamps();

        return $media;
    }

    public function startCompletingMultipartUpload(): void
    {
        $this->status = MediaStatus::CompletingMultipartUpload;
        $this->touch();
    }

    public function markMultipartCompletionFailedCanRetry(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedCanRetry;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markMultipartCompletionFailedNeedReupload(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedNeedReupload;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markUploaded(): void
    {
        $this->status = MediaStatus::Uploaded;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function startProcessing(): void
    {
        $this->status = MediaStatus::Processing;
        $this->touch();
    }

    /**
     * Готовое медиа не «ломается» задним числом: повторная/запоздалая фиксация ошибки на уже
     * ready-медиа — no-op (симметрично guard'у в markReadyMovedTo). Защищает инвариант
     * «ready без ошибки» независимо от вызывающего, даже если фиксацию сбоя задиспатчат в обход
     * isReady-guard'а в ProcessMediaHandler (другой relay, ручной перезапуск Job, дубликат в очереди).
     */
    public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        $this->status = MediaStatus::ProcessingFailed;
        $this->processingAttempts = $this->processingAttempts->increment();
        $this->processingError = $processingError;
        $this->touch();
    }

    /**
     * См. recordTemporaryProcessingError: тот же инвариант «ready без ошибки» — на уже
     * ready-медиа фиксация постоянной ошибки также no-op.
     */
    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        $this->status = MediaStatus::ProcessingFailed;
        $this->processingAttempts = $this->processingAttempts->increment();
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markReady(): void
    {
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    /**
     * Перевод в ready с переназначением целевого хранилища и пути (после перекладки оригинала
     * из staging). Идемпотентен: повторная доставка на ready — no-op. Допустим из uploaded,
     * processing или processingFailed (повтор обработки после временной ошибки).
     */
    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        if (
            $this->status !== MediaStatus::Uploaded
            && $this->status !== MediaStatus::Processing
            && $this->status !== MediaStatus::ProcessingFailed
        ) {
            throw new InvalidDomainValueException(
                'Перевод медиа в ready допустим только из uploaded, processing или processingFailed.',
            );
        }

        $this->storage = $storage;
        $this->path = $path;
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    public function markReadyOriginalRemoved(): void
    {
        $this->status = MediaStatus::ReadyOriginalRemoved;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function makePermanent(): void
    {
        $this->expiration = MediaExpiration::permanent();
        $this->touch();
    }
}
