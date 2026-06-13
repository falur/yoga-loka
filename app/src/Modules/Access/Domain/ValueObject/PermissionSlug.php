<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class PermissionSlug implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $slug = \trim($value);

        if (\preg_match(pattern: '/^[a-z][a-z0-9.]*[a-z0-9]$/', subject: $slug) !== 1) {
            throw new InvalidDomainValueException('Slug права имеет неверный формат.');
        }

        return new self(value: $slug);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $slug): bool
    {
        return $this->value === $slug->value;
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
}
