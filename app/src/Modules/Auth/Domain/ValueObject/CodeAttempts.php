<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Счётчик неверных вводов кода. Код сгорает после LIMIT неудачных попыток.
 * fromInt обязателен для восстановления через конвенцию ValueObjectCast.
 */
final readonly class CodeAttempts implements \Stringable, \JsonSerializable
{
    private const int LIMIT = 5;

    private function __construct(
        private int $value,
    ) {}

    public static function initial(): self
    {
        return new self(value: 0);
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw new InvalidDomainValueException('Число попыток не может быть отрицательным.');
        }

        return new self(value: $value);
    }

    public function increment(): self
    {
        return new self(value: $this->value + 1);
    }

    public function isExhausted(): bool
    {
        return $this->value >= self::LIMIT;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $attempts): bool
    {
        return $this->value === $attempts->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->value;
    }

    #[\Override]
    public function jsonSerialize(): int
    {
        return $this->value;
    }
}
