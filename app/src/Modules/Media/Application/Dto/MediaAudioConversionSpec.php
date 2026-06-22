<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;

/**
 * Профиль одной конверсии аудио. Примитив-дружественный Application-DTO с публичным
 * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
 * поэтому Valinor должен штатно восстанавливать его из enum+int. Handler сам строит из него
 * доменные VO (MediaBitrate/MediaSampleRate) и целевой путь.
 */
final readonly class MediaAudioConversionSpec
{
    public function __construct(
        public MediaAudioConversionType $type,
        public int $bitrate,
        public int $sampleRate,
        public int $waveformPeaks,
    ) {}
}
