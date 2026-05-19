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
            $messages[] = \strtr(
                string: 'путь `{path}`, ожидалось `{expected}`, получено `{actual}`',
                from: [
                    '{path}' => $message->path(),
                    '{expected}' => $message->expectedSignature(),
                    '{actual}' => $message->sourceValue(),
                ],
            );
        }

        return new self(
            message: \strtr(
                string: 'Не удалось преобразовать раздел конфигурации `{section}` в `{targetClass}`: {details}',
                from: [
                    '{section}' => $section,
                    '{targetClass}' => $targetClass,
                    '{details}' => \implode(separator: '; ', array: $messages) ?: $error->getMessage(),
                ],
            ),
            previous: $error,
        );
    }
}
