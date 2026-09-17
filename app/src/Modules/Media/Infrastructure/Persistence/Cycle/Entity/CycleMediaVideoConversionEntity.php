<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaVideoConversionColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Media, см. CycleMediaImageConversionEntity.
 */
#[Entity(
    role: 'media_video_conversion',
    table: MediaVideoConversionColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CycleMediaVideoConversionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: MediaVideoConversionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: MediaVideoConversionColumns::MEDIA_ID)]
    public string $mediaId;

    #[Column(type: 'string(64)', name: MediaVideoConversionColumns::TYPE, typecast: MediaVideoConversionType::class)]
    public MediaVideoConversionType $type;

    #[Column(type: 'string(32)', name: MediaVideoConversionColumns::STATUS, typecast: MediaConversionStatus::class)]
    public MediaConversionStatus $status;

    #[Column(type: 'string(64)', name: MediaVideoConversionColumns::STORAGE, typecast: MediaStorage::class)]
    public MediaStorage $storage;

    #[Column(type: 'string(1024)', name: MediaVideoConversionColumns::PATH)]
    public string $path;

    #[Column(type: 'string(255)', name: MediaVideoConversionColumns::MIME_TYPE)]
    public string $mimeType;

    #[Column(type: 'bigInteger', name: MediaVideoConversionColumns::SIZE)]
    public int $size;

    #[Column(type: 'integer', name: MediaVideoConversionColumns::WIDTH)]
    public int $width;

    #[Column(type: 'integer', name: MediaVideoConversionColumns::HEIGHT)]
    public int $height;

    #[Column(type: 'bigInteger', name: MediaVideoConversionColumns::DURATION_MS)]
    public int $duration;

    #[Column(type: 'integer', name: MediaVideoConversionColumns::BITRATE)]
    public int $bitrate;

    #[BelongsTo(target: CycleMediaEntity::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public CycleMediaEntity $media;
}
