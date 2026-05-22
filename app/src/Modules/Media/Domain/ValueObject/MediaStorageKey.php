<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

final readonly class MediaStorageKey implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self(value: Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value) || Uuid::fromString($value)->getVersion() !== 4) {
            throw new InvalidDomainValueException('Ключ файла должен быть UUID v4.');
        }

        return new self(value: \strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function shard(): string
    {
        return \substr(string: $this->value, offset: 0, length: 2);
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
}
