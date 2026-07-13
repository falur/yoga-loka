<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

abstract readonly class AbstractIntegerValue implements \Stringable, \JsonSerializable
{
    protected const string NAME = 'Значение';

    final protected function __construct(
        private int $value,
    ) {}

    public static function fromInt(int $value): static
    {
        static::assertValid($value);

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

    /**
     * Проверяет доменную валидность значения и бросает InvalidDomainValueException при нарушении.
     * Базовый класс не навязывает диапазон: ограничение задаёт конкретный VO (например, положительность),
     * а для значений с диапазоном [MIN; MAX] — промежуточный AbstractRangedIntegerValue.
     */
    abstract protected static function assertValid(int $value): void;
}
