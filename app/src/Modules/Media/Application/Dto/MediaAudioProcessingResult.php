<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;

/**
 * Результат обработки аудио от MediaAudioProcessorContract. Несёт доменные VO с фактическими
 * метаданными нормализованного аудио и волну амплитуд — потребляются handler-ом сразу для
 * создания MediaAudioConversion; не сериализуется.
 */
final readonly class MediaAudioProcessingResult
{
    public function __construct(
        public MediaMimeType $normalizedMimeType,
        public MediaFileSize $normalizedSize,
        public MediaDuration $duration,
        public MediaBitrate $bitrate,
        public MediaSampleRate $sampleRate,
        public MediaWaveform $waveform,
    ) {}
}
