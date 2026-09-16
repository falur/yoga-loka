<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaVideoConversionId;
use App\Shared\Domain\Trait\HasTimestamps;

/**
 * Внутренняя сущность агрегата Media, см. MediaImageConversion.
 */
final class MediaVideoConversion
{
    use HasTimestamps;

    public private(set) MediaVideoConversionId $id;

    public private(set) MediaId $mediaId;

    public private(set) MediaVideoConversionType $type;

    public private(set) MediaConversionStatus $status;

    public private(set) MediaStorage $storage;

    public private(set) MediaPath $path;

    public private(set) MediaMimeType $mimeType;

    public private(set) MediaFileSize $size;

    public private(set) MediaPixelDimension $width;

    public private(set) MediaPixelDimension $height;

    public private(set) MediaDuration $duration;

    public private(set) MediaBitrate $bitrate;

    public static function create(
        Media $media,
        MediaVideoConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        MediaDuration $duration,
        MediaBitrate $bitrate,
    ): self {
        $conversion = new self();
        $conversion->id = MediaVideoConversionId::generate();
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->initializeTimestamps();

        return $conversion;
    }

    public static function restore(
        MediaVideoConversionId $id,
        MediaId $mediaId,
        MediaVideoConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        MediaDuration $duration,
        MediaBitrate $bitrate,
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
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->createdAt = $createdAt;
        $conversion->updatedAt = $updatedAt;

        return $conversion;
    }
}
