<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Application\Dto\MediaConversionResult;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;

/**
 * Контракт обработчика изображений: ресайз оригинала в конверсию заданного размера и формата.
 *
 * Источник оригинала — байты, прочитанные из S3 (не диск). Результат несёт фактические
 * mimeType/size/width/height, которые требует MediaImageConversion::create().
 */
interface MediaImageProcessorContract
{
    public function resize(
        string $originalContents,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        MediaMimeType $targetMimeType,
    ): MediaConversionResult;
}
