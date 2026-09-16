<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaMultipartUploadColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast\MediaMultipartPartCollectionTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Media: не более одной активной на медиа, своего Cycle Repository
 * нет, выборку по её таблице ведёт CycleMediaRepository. parts — составной JSON (список частей
 * загрузки), MediaMultipartPartCollectionTypecast остаётся Typecast-ом (вторая категория правила
 * переноса значений колонок), поэтому ValueObjectCast нужен в диспетчере. У Media нет HasMany на
 * эту сущность (только BelongsTo в обратную сторону) — MediaMapper её не трогает.
 */
#[Entity(
    role: 'media_multipart_upload',
    table: MediaMultipartUploadColumns::TABLE,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CycleMediaMultipartUploadEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: MediaMultipartUploadColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: MediaMultipartUploadColumns::MEDIA_ID)]
    public string $mediaId;

    #[Column(type: 'string(1024)', name: MediaMultipartUploadColumns::UPLOAD_ID)]
    public string $uploadId;

    #[Column(type: 'integer', name: MediaMultipartUploadColumns::PARTS_COUNT)]
    public int $partsCount;

    #[Column(type: 'bigInteger', name: MediaMultipartUploadColumns::PART_SIZE)]
    public int $partSize;

    #[Column(type: 'bigInteger', name: MediaMultipartUploadColumns::FILE_SIZE)]
    public int $fileSize;

    #[Column(type: 'json', name: MediaMultipartUploadColumns::PARTS, typecast: MediaMultipartPartCollectionTypecast::class)]
    public MediaMultipartPartCollection $parts;

    #[BelongsTo(target: CycleMediaEntity::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public CycleMediaEntity $media;
}
