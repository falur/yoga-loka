<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class BlockUnblockedReason implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 500;

    private function __construct(
        private string|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function of(string $value): self
    {
        $reason = \trim($value);

        if ($reason === '' || \mb_strlen($reason) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Причина разблокировки имеет неверную длину.');
        }

        return new self(value: $reason);
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function equals(self $reason): bool
    {
        return $this->value === $reason->value;
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
