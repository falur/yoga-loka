<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Imagick;

use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionResult;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Infrastructure\Imagick\MediaImageProcessorException;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;

/**
 * Обработчик изображений на Intervention Image v4. Драйвер (imagick по умолчанию, gd как
 * фолбэк) берётся из MediaConfig. Источник оригинала — байты, прочитанные из S3.
 */
final readonly class ImagickMediaImageProcessor implements MediaImageProcessorContract
{
    private ImageManager $imageManager;

    public function __construct(MediaConfig $mediaConfig)
    {
        $this->imageManager = new ImageManager($this->driver($mediaConfig->imageProcessingDriver));
    }

    #[\Override]
    public function resize(
        string $originalContents,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        MediaMimeType $targetMimeType,
    ): MediaConversionResult {
        $image = $this->imageManager->decodeBinary($originalContents);
        $image->cover(width: $width->value(), height: $height->value());

        $encoded = $image->encodeUsingMediaType($targetMimeType->value());
        $contents = (string) $encoded;

        return new MediaConversionResult(
            contents: $contents,
            mimeType: MediaMimeType::fromString($encoded->mimetype()),
            size: MediaFileSize::fromInt(\strlen($contents)),
            width: MediaPixelDimension::fromInt($image->width()),
            height: MediaPixelDimension::fromInt($image->height()),
        );
    }

    private function driver(string $driver): DriverInterface
    {
        return match ($driver) {
            'imagick' => new ImagickDriver(),
            'gd' => new GdDriver(),
            default => throw MediaImageProcessorException::unsupportedDriver($driver),
        };
    }
}
