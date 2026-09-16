<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaAudioConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast\MediaWaveformTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Media, см. CycleMediaImageConversionEntity. waveform — составной
 * JSON (список амплитуд), MediaWaveformTypecast остаётся Typecast-ом (правило переноса значений
 * колонок, вторая категория), поэтому ValueObjectCast нужен в диспетчере — он же вызывает
 * castDatabaseValue()/uncastValue() typecast-класса, реализующего ColumnValueTypecast.
 */
#[Entity(
    role: 'media_audio_conversion',
    table: MediaAudioConversionColumns::TABLE,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CycleMediaAudioConversionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: MediaAudioConversionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: MediaAudioConversionColumns::MEDIA_ID)]
    public string $mediaId;

    #[Column(type: 'string(64)', name: MediaAudioConversionColumns::TYPE, typecast: MediaAudioConversionType::class)]
    public MediaAudioConversionType $type;

    #[Column(type: 'string(32)', name: MediaAudioConversionColumns::STATUS, typecast: MediaConversionStatus::class)]
    public MediaConversionStatus $status;

    #[Column(type: 'string(64)', name: MediaAudioConversionColumns::STORAGE, typecast: MediaStorage::class)]
    public MediaStorage $storage;

    #[Column(type: 'string(1024)', name: MediaAudioConversionColumns::PATH)]
    public string $path;

    #[Column(type: 'string(255)', name: MediaAudioConversionColumns::MIME_TYPE)]
    public string $mimeType;

    #[Column(type: 'bigInteger', name: MediaAudioConversionColumns::SIZE)]
    public int $size;

    #[Column(type: 'bigInteger', name: MediaAudioConversionColumns::DURATION_MS)]
    public int $duration;

    #[Column(type: 'integer', name: MediaAudioConversionColumns::BITRATE)]
    public int $bitrate;

    #[Column(type: 'integer', name: MediaAudioConversionColumns::SAMPLE_RATE)]
    public int $sampleRate;

    #[Column(type: 'json', name: MediaAudioConversionColumns::WAVEFORM, typecast: MediaWaveformTypecast::class)]
    public MediaWaveform $waveform;

    #[BelongsTo(target: CycleMediaEntity::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public CycleMediaEntity $media;
}
