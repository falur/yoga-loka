<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Shared\Domain\Trait\HasTimestamps;

/**
 * Внутренняя сущность агрегата Media: не более одной активной на медиа, см. MediaImageConversion.
 */
final class MediaMultipartUpload
{
    use HasTimestamps;

    public private(set) MediaMultipartUploadId $id;

    public private(set) MediaId $mediaId;

    public private(set) MediaMultipartUploadIdValue $uploadId;

    public private(set) MediaMultipartPartsCount $partsCount;

    public private(set) MediaMultipartPartSize $partSize;

    public private(set) MediaFileSize $fileSize;

    public private(set) MediaMultipartPartCollection $parts;

    public static function create(
        Media $media,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        MediaMultipartPartSize $partSize,
        MediaFileSize $fileSize,
    ): self {
        $multipartUpload = new self();
        $multipartUpload->id = MediaMultipartUploadId::generate();
        $multipartUpload->mediaId = $media->id;
        $multipartUpload->uploadId = $uploadId;
        $multipartUpload->partsCount = $partsCount;
        $multipartUpload->partSize = $partSize;
        $multipartUpload->fileSize = $fileSize;
        $multipartUpload->parts = new MediaMultipartPartCollection();
        $multipartUpload->initializeTimestamps();

        return $multipartUpload;
    }

    public static function restore(
        MediaMultipartUploadId $id,
        MediaId $mediaId,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        MediaMultipartPartSize $partSize,
        MediaFileSize $fileSize,
        MediaMultipartPartCollection $parts,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $multipartUpload = new self();
        $multipartUpload->id = $id;
        $multipartUpload->mediaId = $mediaId;
        $multipartUpload->uploadId = $uploadId;
        $multipartUpload->partsCount = $partsCount;
        $multipartUpload->partSize = $partSize;
        $multipartUpload->fileSize = $fileSize;
        $multipartUpload->parts = $parts;
        $multipartUpload->createdAt = $createdAt;
        $multipartUpload->updatedAt = $updatedAt;

        return $multipartUpload;
    }

    public function replaceParts(MediaMultipartPartCollection $parts): void
    {
        $this->parts = $parts;
        $this->touch();
    }
}
