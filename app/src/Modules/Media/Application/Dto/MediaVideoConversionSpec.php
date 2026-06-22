<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

/**
 * Профиль одной конверсии видео. Примитив-дружественный Application-DTO с публичным
 * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
 * поэтому Valinor должен штатно восстанавливать его из enum+int. Handler сам строит из него
 * доменные VO (MediaPixelDimension/MediaBitrate) и целевые пути.
 */
final readonly class MediaVideoConversionSpec
{
    public function __construct(
        public MediaVideoConversionType $type,
        public int $width,
        public int $height,
        public int $videoBitrate,
        public int $audioBitrate,
    ) {}
}
