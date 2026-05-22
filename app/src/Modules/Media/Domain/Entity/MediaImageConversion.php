<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaImageConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Infrastructure\Cycle\MediaValueObjectTypecast;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_image_conversion',
    table: 'media_image_conversions',
    repository: MediaImageConversionRepository::class,
    typecast: [Typecast::class, MediaValueObjectTypecast::class],
)]
final class MediaImageConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaImageConversionId::class)]
    public private(set) MediaImageConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaImageConversionType::class)]
    public private(set) MediaImageConversionType $type;

    #[Column(type: 'string(32)', typecast: MediaConversionStatus::class)]
    public private(set) MediaConversionStatus $status;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $width;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $height;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

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
        $conversion->media = $media;
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
}
