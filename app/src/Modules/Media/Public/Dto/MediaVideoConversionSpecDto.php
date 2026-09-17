<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

use App\Modules\Media\Public\Enum\MediaVideoConversionType;

/**
 * Профиль одной конверсии видео. Примитив-дружественный публичный DTO с публичным
 * конструктором: переиспользуется в событии MediaUploadedEvent (через MediaConversionPlanDto),
 * поэтому Valinor должен штатно восстанавливать его из enum+int. Handler сам строит из него
 * доменные VO (MediaPixelDimension/MediaBitrate) и целевые пути.
 */
final readonly class MediaVideoConversionSpecDto
{
    public function __construct(
        public MediaVideoConversionType $type,
        public int $width,
        public int $height,
        public int $videoBitrate,
        public int $audioBitrate,
    ) {}
}
