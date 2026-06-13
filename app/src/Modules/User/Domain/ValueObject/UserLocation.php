<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserLocation implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 100;

    private function __construct(
        private string|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function fromString(string $value): self
    {
        $location = \trim($value);

        if ($location === '' || \mb_strlen($location) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Местоположение имеет неверную длину.');
        }

        if (\preg_match(pattern: '/^[\p{L}\p{N} .,-]+$/u', subject: $location) !== 1) {
            throw new InvalidDomainValueException('Местоположение содержит недопустимые символы.');
        }

        return new self(value: $location);
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function equals(self $location): bool
    {
        return $this->value === $location->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->value;
    }
}
