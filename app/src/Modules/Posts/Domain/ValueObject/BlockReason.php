<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class BlockReason implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 500;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $reason = \trim($value);

        if ($reason === '' || \mb_strlen($reason) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Причина блокировки имеет неверную длину.');
        }

        return new self(value: $reason);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $reason): bool
    {
        return $this->value === $reason->value;
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
