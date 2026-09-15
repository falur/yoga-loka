<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Imagick;

final class MediaImageProcessorException extends \DomainException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self(\sprintf('Неподдерживаемый драйвер обработки изображений: %s.', $driver));
    }
}
