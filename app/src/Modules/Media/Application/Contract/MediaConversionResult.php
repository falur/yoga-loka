<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;

/**
 * Результат конверсии изображения от MediaImageProcessorContract. Несёт байты результата и
 * фактические mimeType/size/width/height — они нужны для MediaImageConversion::create()
 * (в MediaImageConversionSpecDto их нет).
 */
final readonly class MediaConversionResult
{
    public function __construct(
        public string $contents,
        public MediaMimeType $mimeType,
        public MediaFileSize $size,
        public MediaPixelDimension $width,
        public MediaPixelDimension $height,
    ) {}
}
