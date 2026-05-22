<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Infrastructure\Cycle\MediaMultipartPartCollectionTypecast;
use App\Modules\Media\Infrastructure\Cycle\MediaValueObjectTypecast;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_multipart_upload',
    table: 'media_multipart_uploads',
    repository: MediaMultipartUploadRepository::class,
    typecast: [Typecast::class, MediaValueObjectTypecast::class],
)]
final class MediaMultipartUpload
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaMultipartUploadId::class)]
    public private(set) MediaMultipartUploadId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(1024)', name: 'upload_id', typecast: MediaMultipartUploadIdValue::class)]
    public private(set) MediaMultipartUploadIdValue $uploadId;

    #[Column(type: 'integer', name: 'parts_count', typecast: MediaMultipartPartsCount::class)]
    public private(set) MediaMultipartPartsCount $partsCount;

    #[Column(type: 'bigInteger', name: 'part_size', typecast: MediaMultipartPartSize::class)]
    public private(set) MediaMultipartPartSize $partSize;

    #[Column(type: 'bigInteger', name: 'file_size', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $fileSize;

    #[Column(type: 'json', typecast: MediaMultipartPartCollectionTypecast::class)]
    public private(set) MediaMultipartPartCollection $parts;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

    public static function create(
        Media $media,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        MediaMultipartPartSize $partSize,
        MediaFileSize $fileSize,
    ): self {
        $multipartUpload = new self();
        $multipartUpload->id = MediaMultipartUploadId::generate();
        $multipartUpload->media = $media;
        $multipartUpload->mediaId = $media->id;
        $multipartUpload->uploadId = $uploadId;
        $multipartUpload->partsCount = $partsCount;
        $multipartUpload->partSize = $partSize;
        $multipartUpload->fileSize = $fileSize;
        $multipartUpload->parts = new MediaMultipartPartCollection();
        $multipartUpload->initializeTimestamps();

        return $multipartUpload;
    }

    public function replaceParts(MediaMultipartPartCollection $parts): void
    {
        $this->parts = $parts;
        $this->touch();
    }
}
