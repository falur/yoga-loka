<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Locale;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class LocaleConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'locale';
    }

    /**
     * @param list<string> $supported
     */
    public function __construct(
        public array $supported,
        public string $default,
    ) {
        // Базовая локаль обязана входить в белый список: иначе резолв при отсутствии совпадений
        // выставил бы translator в неподдерживаемую локаль без каталога переводов.
        if (!\in_array(needle: $default, haystack: $supported, strict: true)) {
            throw new InvalidConfigValueException(
                path: 'locale.default',
                expected: \sprintf('одна из поддерживаемых (%s)', \implode(separator: ', ', array: $supported)),
                actual: $default,
            );
        }
    }
}
