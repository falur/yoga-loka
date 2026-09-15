<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaAudioConversionId;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast\MediaWaveformTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_audio_conversion',
    table: 'media_audio_conversions',
    repository: MediaAudioConversionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class MediaAudioConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaAudioConversionId::class)]
    public private(set) MediaAudioConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaAudioConversionType::class)]
    public private(set) MediaAudioConversionType $type;

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

    #[Column(type: 'bigInteger', name: 'duration_ms', typecast: MediaDuration::class)]
    public private(set) MediaDuration $duration;

    #[Column(type: 'integer', typecast: MediaBitrate::class)]
    public private(set) MediaBitrate $bitrate;

    #[Column(type: 'integer', name: 'sample_rate', typecast: MediaSampleRate::class)]
    public private(set) MediaSampleRate $sampleRate;

    #[Column(type: 'json', typecast: MediaWaveformTypecast::class)]
    public private(set) MediaWaveform $waveform;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

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
        $conversion->media = $media;
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
}
