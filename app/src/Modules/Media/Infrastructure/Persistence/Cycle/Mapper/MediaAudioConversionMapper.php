<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaAudioConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaAudioConversionEntity;

final readonly class MediaAudioConversionMapper
{
    public function toDomain(CycleMediaAudioConversionEntity $cycleEntity): MediaAudioConversion
    {
        return MediaAudioConversion::restore(
            id: MediaAudioConversionId::fromString($cycleEntity->id),
            mediaId: MediaId::fromString($cycleEntity->mediaId),
            type: $cycleEntity->type,
            status: $cycleEntity->status,
            storage: $cycleEntity->storage,
            path: MediaPath::fromString($cycleEntity->path),
            mimeType: MediaMimeType::fromString($cycleEntity->mimeType),
            size: MediaFileSize::fromInt($cycleEntity->size),
            duration: MediaDuration::fromInt($cycleEntity->duration),
            bitrate: MediaBitrate::fromInt($cycleEntity->bitrate),
            sampleRate: MediaSampleRate::fromInt($cycleEntity->sampleRate),
            waveform: $cycleEntity->waveform,
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        MediaAudioConversion $audioConversion,
        CycleMediaAudioConversionEntity|null $cycleEntity = null,
    ): CycleMediaAudioConversionEntity {
        $cycleEntity ??= new CycleMediaAudioConversionEntity();
        $cycleEntity->id = $audioConversion->id->value();
        $cycleEntity->mediaId = $audioConversion->mediaId->value();
        $cycleEntity->type = $audioConversion->type;
        $cycleEntity->status = $audioConversion->status;
        $cycleEntity->storage = $audioConversion->storage;
        $cycleEntity->path = $audioConversion->path->value();
        $cycleEntity->mimeType = $audioConversion->mimeType->value();
        $cycleEntity->size = $audioConversion->size->value();
        $cycleEntity->duration = $audioConversion->duration->value();
        $cycleEntity->bitrate = $audioConversion->bitrate->value();
        $cycleEntity->sampleRate = $audioConversion->sampleRate->value();
        $cycleEntity->waveform = $audioConversion->waveform;
        $cycleEntity->createdAt = $audioConversion->createdAt;
        $cycleEntity->updatedAt = $audioConversion->updatedAt;

        return $cycleEntity;
    }
}
