<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Exception;

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
                    '{expected}' => \trim(string: $message->expectedSignature(), characters: '`'),
                    '{actual}' => self::actualType(
                        error: $error,
                        path: $message->path(),
                        fallbackType: $message->type(),
                    ),
                ],
            );
        }

        return new self(
            message: \strtr(
                string: 'Не удалось преобразовать раздел конфигурации `{section}` в `{targetClass}`: {details}',
                from: [
                    '{section}' => $section,
                    '{targetClass}' => $targetClass,
                    '{details}' => \implode(separator: '; ', array: $messages) ?: 'подробности недоступны',
                ],
            ),
            previous: $error,
        );
    }

    public static function fromInvalidConfigValue(
        string $section,
        string $targetClass,
        InvalidConfigValueException $error,
    ): self {
        return new self(
            message: \strtr(
                string: 'Не удалось преобразовать раздел конфигурации `{section}` в `{targetClass}`: путь `{path}`, ожидалось `{expected}`, получено `{actual}`',
                from: [
                    '{section}' => $section,
                    '{targetClass}' => $targetClass,
                    '{path}' => $error->path,
                    '{expected}' => $error->expected,
                    '{actual}' => $error->actual,
                ],
            ),
            previous: $error,
        );
    }

    private static function actualType(MappingError $error, string $path, string $fallbackType): string
    {
        $source = $error->source();

        foreach (\explode(separator: '.', string: $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (!\is_array($source)) {
                return $fallbackType;
            }

            if (\array_key_exists(key: $segment, array: $source)) {
                $source = $source[$segment];

                continue;
            }

            if (\ctype_digit($segment) && \array_key_exists(key: (int) $segment, array: $source)) {
                $source = $source[(int) $segment];

                continue;
            }

            return $fallbackType;
        }

        return \get_debug_type($source);
    }
}
