<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadId;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaMultipartUploadEntity;

final readonly class MediaMultipartUploadMapper
{
    public function toDomain(CycleMediaMultipartUploadEntity $cycleEntity): MediaMultipartUpload
    {
        return MediaMultipartUpload::restore(
            id: MediaMultipartUploadId::fromString($cycleEntity->id),
            mediaId: MediaId::fromString($cycleEntity->mediaId),
            uploadId: MediaMultipartUploadIdValue::fromString($cycleEntity->uploadId),
            partsCount: MediaMultipartPartsCount::fromInt($cycleEntity->partsCount),
            partSize: MediaMultipartPartSize::fromInt($cycleEntity->partSize),
            fileSize: MediaFileSize::fromInt($cycleEntity->fileSize),
            parts: $cycleEntity->parts,
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        MediaMultipartUpload $multipartUpload,
        CycleMediaMultipartUploadEntity|null $cycleEntity = null,
    ): CycleMediaMultipartUploadEntity {
        $cycleEntity ??= new CycleMediaMultipartUploadEntity();
        $cycleEntity->id = $multipartUpload->id->value();
        $cycleEntity->mediaId = $multipartUpload->mediaId->value();
        $cycleEntity->uploadId = $multipartUpload->uploadId->value();
        $cycleEntity->partsCount = $multipartUpload->partsCount->value();
        $cycleEntity->partSize = $multipartUpload->partSize->value();
        $cycleEntity->fileSize = $multipartUpload->fileSize->value();
        $cycleEntity->parts = $multipartUpload->parts;
        $cycleEntity->createdAt = $multipartUpload->createdAt;
        $cycleEntity->updatedAt = $multipartUpload->updatedAt;

        return $cycleEntity;
    }
}
