<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

/**
 * User-Agent устройства сессии. Отсутствие/пустота выражается типом UnknownUserAgent, а не null
 * (rules.md «Явные типы вместо null»). НЕ реализует Stringable осознанно: у UnknownUserAgent нет
 * безопасного строкового представления. Чтение наружу — только через
 * jsonSerialize()/toNullableString(), где отсутствие явно выражено null.
 */
abstract readonly class UserAgent implements \JsonSerializable
{
    public static function fromNullable(string|null $value): self
    {
        if ($value === null) {
            return new UnknownUserAgent();
        }

        $normalized = \trim($value);

        if ($normalized === '') {
            return new UnknownUserAgent();
        }

        return KnownUserAgent::fromString($normalized);
    }

    abstract public function toNullableString(): string|null;

    abstract public function equals(self $other): bool;

    #[\Override]
    abstract public function jsonSerialize(): string|null;
}
