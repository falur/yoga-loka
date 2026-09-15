<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

use App\Modules\Media\Public\Enum\MediaAudioConversionType;

/**
 * Профиль одной конверсии аудио. Примитив-дружественный публичный DTO с публичным
 * конструктором: переиспользуется в событии MediaUploadedEvent (через MediaConversionPlanDto),
 * поэтому Valinor должен штатно восстанавливать его из enum+int. Handler сам строит из него
 * доменные VO (MediaBitrate/MediaSampleRate) и целевой путь.
 */
final readonly class MediaAudioConversionSpecDto
{
    public function __construct(
        public MediaAudioConversionType $type,
        public int $bitrate,
        public int $sampleRate,
        public int $waveformPeaks,
    ) {}
}
