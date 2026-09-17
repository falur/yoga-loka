<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Repository\CycleMediaRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

/**
 * Три HasMany-связи (imageConversions/videoConversions/audioConversions) — реальные Cycle-relation
 * корня к внутренним сущностям агрегата (target — их собственные Cycle Entity), не приём выборки
 * отдельным запросом, как у Access/RolePermission: CycleMediaRepository::findByIdsWithConversions()
 * грузит их through ->load(). collection: сохраняет прежний класс домена MediaImageConversionCollection
 * и т.п. без изменений — в момент eager-загрузки Cycle временно кладёт в него Cycle{Name}Entity
 * элементы, MediaMapper::toDomain() тут же превращает их в доменные MediaImageConversion и т.д.
 */
#[Entity(
    role: 'media',
    table: MediaColumns::TABLE,
    repository: CycleMediaRepository::class,
    typecast: [Typecast::class],
)]
final class CycleMediaEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: MediaColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: MediaColumns::STORAGE_KEY)]
    public string $storageKey;

    #[Column(type: 'string(32)', name: MediaColumns::TYPE, typecast: MediaType::class)]
    public MediaType $type;

    #[Column(type: 'string(64)', name: MediaColumns::STATUS, typecast: MediaStatus::class)]
    public MediaStatus $status;

    #[Column(type: 'string(16)', name: MediaColumns::VISIBILITY, typecast: MediaVisibility::class)]
    public MediaVisibility $visibility;

    #[Column(type: 'string(64)', name: MediaColumns::STORAGE, typecast: MediaStorage::class)]
    public MediaStorage $storage;

    #[Column(type: 'string(1024)', name: MediaColumns::PATH)]
    public string $path;

    #[Column(type: 'string(255)', name: MediaColumns::MIME_TYPE)]
    public string $mimeType;

    #[Column(type: 'bigInteger', name: MediaColumns::SIZE)]
    public int $size;

    #[Column(type: 'uuid', name: MediaColumns::UPLOADED_BY_ID)]
    public string $uploadedById;

    #[Column(type: 'datetime', name: MediaColumns::EXPIRES_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $expiresAt;

    #[Column(type: 'integer', name: MediaColumns::PROCESSING_ATTEMPTS)]
    public int $processingAttempts;

    #[Column(type: 'text', name: MediaColumns::PROCESSING_ERROR, nullable: true)]
    public string|null $processingError;

    #[HasMany(
        target: CycleMediaImageConversionEntity::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaImageConversionCollection::class,
    )]
    public MediaImageConversionCollection $imageConversions;

    #[HasMany(
        target: CycleMediaVideoConversionEntity::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaVideoConversionCollection::class,
    )]
    public MediaVideoConversionCollection $videoConversions;

    #[HasMany(
        target: CycleMediaAudioConversionEntity::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaAudioConversionCollection::class,
    )]
    public MediaAudioConversionCollection $audioConversions;
}
