<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

use App\Modules\Media\Public\Enum\MediaImageConversionType;

/**
 * Профиль одной конверсии изображения. Примитив-дружественный публичный DTO с публичным
 * конструктором: переиспользуется в событии MediaUploadedEvent (через MediaConversionPlanDto),
 * поэтому Valinor должен штатно восстанавливать его из enum+int+int (без приватных фабрик
 * доменных VO). Handler сам строит из него MediaImageConversionType/MediaPixelDimension.
 */
final readonly class MediaImageConversionSpecDto
{
    public function __construct(
        public MediaImageConversionType $type,
        public int $width,
        public int $height,
    ) {}
}
