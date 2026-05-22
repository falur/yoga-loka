<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

abstract readonly class AbstractIntegerValue implements \Stringable, \JsonSerializable
{
    protected const int MIN = 0;
    protected const int MAX = 0;
    protected const string NAME = 'Значение';

    final protected function __construct(
        private int $value,
    ) {}

    public static function fromInt(int $value): static
    {
        static::assertInRange($value);

        return new static(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
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

    protected static function assertInRange(int $value): void
    {
        if ($value < static::MIN || $value > static::MAX) {
            throw new InvalidDomainValueException(
                \sprintf('%s должно быть от %d до %d.', static::NAME, static::MIN, static::MAX),
            );
        }
    }
}
