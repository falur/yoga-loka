<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Известный User-Agent клиента. Значение нормализуется (trim) и обрезается до разумного предела,
 * чтобы аномально длинный заголовок не раздувал строку токена.
 */
final readonly class KnownUserAgent extends UserAgent
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = \trim($value);

        if ($normalized === '') {
            throw new InvalidDomainValueException('User-Agent не может быть пустым.');
        }

        return new self(value: \mb_substr(string: $normalized, start: 0, length: self::MAX_LENGTH));
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
    public function equals(UserAgent $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
