<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaAudioConversionId;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Trait\HasTimestamps;

/**
 * Внутренняя сущность агрегата Media, см. MediaImageConversion.
 */
final class MediaAudioConversion
{
    use HasTimestamps;

    public private(set) MediaAudioConversionId $id;

    public private(set) MediaId $mediaId;

    public private(set) MediaAudioConversionType $type;

    public private(set) MediaConversionStatus $status;

    public private(set) MediaStorage $storage;

    public private(set) MediaPath $path;

    public private(set) MediaMimeType $mimeType;

    public private(set) MediaFileSize $size;

    public private(set) MediaDuration $duration;

    public private(set) MediaBitrate $bitrate;

    public private(set) MediaSampleRate $sampleRate;

    public private(set) MediaWaveform $waveform;

    public static function create(
        Media $media,
        MediaAudioConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaDuration $duration,
        MediaBitrate $bitrate,
        MediaSampleRate $sampleRate,
        MediaWaveform $waveform,
    ): self {
        $conversion = new self();
        $conversion->id = MediaAudioConversionId::generate();
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->sampleRate = $sampleRate;
        $conversion->waveform = $waveform;
        $conversion->initializeTimestamps();

        return $conversion;
    }

    public static function restore(
        MediaAudioConversionId $id,
        MediaId $mediaId,
        MediaAudioConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaDuration $duration,
        MediaBitrate $bitrate,
        MediaSampleRate $sampleRate,
        MediaWaveform $waveform,
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
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->sampleRate = $sampleRate;
        $conversion->waveform = $waveform;
        $conversion->createdAt = $createdAt;
        $conversion->updatedAt = $updatedAt;

        return $conversion;
    }
}
