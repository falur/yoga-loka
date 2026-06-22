<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;

/**
 * Результат обработки видео от MediaVideoProcessorContract. Несёт доменные VO с фактическими
 * метаданными нормализованного видео и постера — потребляются handler-ом сразу для создания
 * MediaVideoConversion и MediaImageConversion (Poster); не сериализуется. Пиксельный размер
 * постера равен размеру транскода (width/height), отдельных полей нет.
 */
final readonly class MediaVideoProcessingResult
{
    public function __construct(
        public MediaMimeType $normalizedMimeType,
        public MediaFileSize $normalizedSize,
        public MediaPixelDimension $width,
        public MediaPixelDimension $height,
        public MediaDuration $duration,
        public MediaBitrate $bitrate,
        public MediaMimeType $posterMimeType,
        public MediaFileSize $posterSize,
    ) {}
}
