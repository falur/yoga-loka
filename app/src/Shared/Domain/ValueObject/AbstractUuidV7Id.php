<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

abstract readonly class AbstractUuidV7Id implements \Stringable, \JsonSerializable
{
    final protected function __construct(
        private string $value,
    ) {}

    public static function generate(): static
    {
        return new static(value: Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        self::assertUuidV7($value);

        return new static(value: \strtolower($value));
    }

    public function value(): string
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
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public static function isUuidV7(string $value): bool
    {
        return Uuid::isValid($value) && Uuid::fromString($value)->getVersion() === 7;
    }

    private static function assertUuidV7(string $value): void
    {
        if (!self::isUuidV7($value)) {
            throw new InvalidDomainValueException('Идентификатор должен быть UUID v7.');
        }
    }
}
