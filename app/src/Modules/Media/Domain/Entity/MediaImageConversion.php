<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaImageConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Domain\Trait\HasTimestamps;

/**
 * Внутренняя сущность агрегата Media: существует только у своего медиа, создаётся и удаляется
 * вместе с ним, отдельного репозитория не имеет. Ссылка на медиа хранится только как mediaId —
 * объект Media не хранится (в форме хранения это была связь BelongsTo к корню, нужная лишь для
 * каскада и схемы, а не доменному поведению: ни один потребитель Application/Infrastructure не
 * читал это поле).
 */
final class MediaImageConversion
{
    use HasTimestamps;

    public private(set) MediaImageConversionId $id;

    public private(set) MediaId $mediaId;

    public private(set) MediaImageConversionType $type;

    public private(set) MediaConversionStatus $status;

    public private(set) MediaStorage $storage;

    public private(set) MediaPath $path;

    public private(set) MediaMimeType $mimeType;

    public private(set) MediaFileSize $size;

    public private(set) MediaPixelDimension $width;

    public private(set) MediaPixelDimension $height;

    public static function create(
        Media $media,
        MediaImageConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
    ): self {
        $conversion = new self();
        $conversion->id = MediaImageConversionId::generate();
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->initializeTimestamps();

        return $conversion;
    }

    public static function restore(
        MediaImageConversionId $id,
        MediaId $mediaId,
        MediaImageConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $conversion = new self();
        $conversion->id = $id;
        $conversion->mediaId = $mediaId;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->createdAt = $createdAt;
        $conversion->updatedAt = $updatedAt;

        return $conversion;
    }
}
