<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Mapping;

use CuyZ\Valinor\Mapper\MappingError;

final class ConfigMappingException extends \RuntimeException
{
    public static function fromMappingError(string $section, string $targetClass, MappingError $error): self
    {
        $messages = [];

        foreach ($error->messages() as $message) {
            $messages[] = 'путь `' . $message->path() . '`, ожидалось `'
                . $message->expectedSignature() . '`, получено `' . $message->sourceValue() . '`';
        }

        return new self(
            message: 'Не удалось преобразовать раздел конфигурации `' . $section . '` в `' . $targetClass . '`: '
                . (\implode(separator: '; ', array: $messages) ?: $error->getMessage()),
            previous: $error,
        );
    }
}
