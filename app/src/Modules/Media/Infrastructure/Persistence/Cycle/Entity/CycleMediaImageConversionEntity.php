<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaImageConversionColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Media: своего Cycle Repository нет, выборку по её таблице ведёт
 * CycleMediaRepository (MediaRepository::findImageConversionsByMediaId()/hasReadyImageConversion()).
 * BelongsTo сохранён буквально для той же схемы/каскада, что и до разделения, но MediaImageConversionMapper
 * его не трогает: связь с медиа отдаётся полем mediaId, доменная сущность не хранит объект Media.
 */
#[Entity(
    role: 'media_image_conversion',
    table: MediaImageConversionColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CycleMediaImageConversionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: MediaImageConversionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: MediaImageConversionColumns::MEDIA_ID)]
    public string $mediaId;

    #[Column(type: 'string(64)', name: MediaImageConversionColumns::TYPE, typecast: MediaImageConversionType::class)]
    public MediaImageConversionType $type;

    #[Column(type: 'string(32)', name: MediaImageConversionColumns::STATUS, typecast: MediaConversionStatus::class)]
    public MediaConversionStatus $status;

    #[Column(type: 'string(64)', name: MediaImageConversionColumns::STORAGE, typecast: MediaStorage::class)]
    public MediaStorage $storage;

    #[Column(type: 'string(1024)', name: MediaImageConversionColumns::PATH)]
    public string $path;

    #[Column(type: 'string(255)', name: MediaImageConversionColumns::MIME_TYPE)]
    public string $mimeType;

    #[Column(type: 'bigInteger', name: MediaImageConversionColumns::SIZE)]
    public int $size;

    #[Column(type: 'integer', name: MediaImageConversionColumns::WIDTH)]
    public int $width;

    #[Column(type: 'integer', name: MediaImageConversionColumns::HEIGHT)]
    public int $height;

    #[BelongsTo(target: CycleMediaEntity::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public CycleMediaEntity $media;
}
