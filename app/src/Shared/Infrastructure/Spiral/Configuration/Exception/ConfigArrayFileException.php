<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Exception;

final class ConfigArrayFileException extends \RuntimeException
{
    public static function notAnArray(string $path): self
    {
        return new self(\sprintf('Файл конфигурации `%s` должен возвращать массив.', $path));
    }
}
