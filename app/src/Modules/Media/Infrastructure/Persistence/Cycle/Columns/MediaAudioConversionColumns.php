<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Columns;

final class MediaAudioConversionColumns
{
    public const string TABLE = 'media_audio_conversions';

    public const string ID = 'id';
    public const string MEDIA_ID = 'media_id';
    public const string TYPE = 'type';
    public const string STATUS = 'status';
    public const string STORAGE = 'storage';
    public const string PATH = 'path';
    public const string MIME_TYPE = 'mime_type';
    public const string SIZE = 'size';
    public const string DURATION_MS = 'duration_ms';
    public const string BITRATE = 'bitrate';
    public const string SAMPLE_RATE = 'sample_rate';
    public const string WAVEFORM = 'waveform';

    private function __construct() {}
}
