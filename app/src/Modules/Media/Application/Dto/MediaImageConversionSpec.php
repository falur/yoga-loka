<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Enum\MediaImageConversionType;

/**
 * Профиль одной конверсии изображения. Примитив-дружественный Application-DTO с публичным
 * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
 * поэтому Valinor должен штатно восстанавливать его из enum+int+int (без приватных фабрик
 * доменных VO). Handler сам строит из него MediaImageConversionType/MediaPixelDimension.
 */
final readonly class MediaImageConversionSpec
{
    public function __construct(
        public MediaImageConversionType $type,
        public int $width,
        public int $height,
    ) {}
}
