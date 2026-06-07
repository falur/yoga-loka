<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

final readonly class EmptyOutboxEventDate extends OutboxEventDate
{
    #[\Override]
    public function isEmpty(): bool
    {
        return true;
    }

    #[\Override]
    public function equals(OutboxEventDate $other): bool
    {
        return $other->isEmpty();
    }

    #[\Override]
    public function __toString(): string
    {
        return '';
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return '';
    }
}
