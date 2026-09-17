<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaVideoConversionId;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaVideoConversionEntity;

final readonly class MediaVideoConversionMapper
{
    public function toDomain(CycleMediaVideoConversionEntity $cycleEntity): MediaVideoConversion
    {
        return MediaVideoConversion::restore(
            id: MediaVideoConversionId::fromString($cycleEntity->id),
            mediaId: MediaId::fromString($cycleEntity->mediaId),
            type: $cycleEntity->type,
            status: $cycleEntity->status,
            storage: $cycleEntity->storage,
            path: MediaPath::fromString($cycleEntity->path),
            mimeType: MediaMimeType::fromString($cycleEntity->mimeType),
            size: MediaFileSize::fromInt($cycleEntity->size),
            width: MediaPixelDimension::fromInt($cycleEntity->width),
            height: MediaPixelDimension::fromInt($cycleEntity->height),
            duration: MediaDuration::fromInt($cycleEntity->duration),
            bitrate: MediaBitrate::fromInt($cycleEntity->bitrate),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        MediaVideoConversion $videoConversion,
        CycleMediaVideoConversionEntity|null $cycleEntity = null,
    ): CycleMediaVideoConversionEntity {
        $cycleEntity ??= new CycleMediaVideoConversionEntity();
        $cycleEntity->id = $videoConversion->id->value();
        $cycleEntity->mediaId = $videoConversion->mediaId->value();
        $cycleEntity->type = $videoConversion->type;
        $cycleEntity->status = $videoConversion->status;
        $cycleEntity->storage = $videoConversion->storage;
        $cycleEntity->path = $videoConversion->path->value();
        $cycleEntity->mimeType = $videoConversion->mimeType->value();
        $cycleEntity->size = $videoConversion->size->value();
        $cycleEntity->width = $videoConversion->width->value();
        $cycleEntity->height = $videoConversion->height->value();
        $cycleEntity->duration = $videoConversion->duration->value();
        $cycleEntity->bitrate = $videoConversion->bitrate->value();
        $cycleEntity->createdAt = $videoConversion->createdAt;
        $cycleEntity->updatedAt = $videoConversion->updatedAt;

        return $cycleEntity;
    }
}
