<?php

declare(strict_types=1);

namespace App\Shared\Domain\Locale;

/**
 * Доменный сервис разбора локали: сводит запрошенный код к поддерживаемому или к значению по
 * умолчанию. Поддерживаемые локали и значение по умолчанию приходят готовыми из Infrastructure
 * (бутлоадер собирает сервис из LocaleConfig), поэтому Application не знает про конфиг.
 *
 * Возвращает строку (код локали), а не enum Locale: результат идёт прямо в translator->setLocale,
 * а потребителю, которому нужен enum, проще обернуть результат в Locale::from() у себя.
 */
final readonly class LocaleResolver
{
    /**
     * @param list<string> $supported
     */
    public function __construct(
        private array $supported,
        private string $default,
    ) {}

    public function resolve(string $locale): string
    {
        return \in_array(needle: $locale, haystack: $this->supported, strict: true)
            ? $locale
            : $this->default;
    }
}
