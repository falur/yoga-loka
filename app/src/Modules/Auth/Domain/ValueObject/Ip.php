<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

/**
 * IP-адрес устройства сессии. Отсутствие/невалидность выражается типом UnknownIp, а не null
 * (rules.md «Явные типы вместо null»). НЕ реализует Stringable осознанно: у UnknownIp нет
 * безопасного строкового представления, __toString вернул бы пустую строку и замаскировал
 * отсутствие значения. Чтение наружу — только через jsonSerialize()/toNullableString(), где
 * отсутствие явно выражено null.
 */
abstract readonly class Ip implements \JsonSerializable
{
    public static function fromNullable(string|null $value): self
    {
        if ($value === null) {
            return new UnknownIp();
        }

        $normalized = \trim($value);

        if ($normalized === '' || !\filter_var(value: $normalized, filter: FILTER_VALIDATE_IP)) {
            return new UnknownIp();
        }

        return KnownIp::fromString($normalized);
    }

    abstract public function toNullableString(): string|null;

    abstract public function equals(self $other): bool;

    #[\Override]
    abstract public function jsonSerialize(): string|null;
}
