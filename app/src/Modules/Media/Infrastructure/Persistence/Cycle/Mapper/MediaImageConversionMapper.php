<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaImageConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaImageConversionEntity;

final readonly class MediaImageConversionMapper
{
    public function toDomain(CycleMediaImageConversionEntity $cycleEntity): MediaImageConversion
    {
        return MediaImageConversion::restore(
            id: MediaImageConversionId::fromString($cycleEntity->id),
            mediaId: MediaId::fromString($cycleEntity->mediaId),
            type: $cycleEntity->type,
            status: $cycleEntity->status,
            storage: $cycleEntity->storage,
            path: MediaPath::fromString($cycleEntity->path),
            mimeType: MediaMimeType::fromString($cycleEntity->mimeType),
            size: MediaFileSize::fromInt($cycleEntity->size),
            width: MediaPixelDimension::fromInt($cycleEntity->width),
            height: MediaPixelDimension::fromInt($cycleEntity->height),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        MediaImageConversion $imageConversion,
        CycleMediaImageConversionEntity|null $cycleEntity = null,
    ): CycleMediaImageConversionEntity {
        $cycleEntity ??= new CycleMediaImageConversionEntity();
        $cycleEntity->id = $imageConversion->id->value();
        $cycleEntity->mediaId = $imageConversion->mediaId->value();
        $cycleEntity->type = $imageConversion->type;
        $cycleEntity->status = $imageConversion->status;
        $cycleEntity->storage = $imageConversion->storage;
        $cycleEntity->path = $imageConversion->path->value();
        $cycleEntity->mimeType = $imageConversion->mimeType->value();
        $cycleEntity->size = $imageConversion->size->value();
        $cycleEntity->width = $imageConversion->width->value();
        $cycleEntity->height = $imageConversion->height->value();
        $cycleEntity->createdAt = $imageConversion->createdAt;
        $cycleEntity->updatedAt = $imageConversion->updatedAt;

        return $cycleEntity;
    }
}
