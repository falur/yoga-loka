<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

final readonly class KnownOutboxEventDate extends OutboxEventDate
{
    use ComparesDateTimeToMicroseconds;

    protected function __construct(
        private \DateTimeImmutable $value,
    ) {}

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return false;
    }

    #[\Override]
    public function equals(OutboxEventDate $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return self::dateTimeEqualsToMicroseconds(value: $this->value, other: $other->value);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }
}
