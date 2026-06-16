<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Известный валидный IP-адрес (IPv4 или IPv6). Создаётся только через фабрику с валидацией.
 */
final readonly class KnownIp extends Ip
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = \trim($value);

        if (!\filter_var(value: $normalized, filter: FILTER_VALIDATE_IP)) {
            throw new InvalidDomainValueException('Некорректный IP-адрес.');
        }

        return new self(value: $normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    #[\Override]
    public function toNullableString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function equals(Ip $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
