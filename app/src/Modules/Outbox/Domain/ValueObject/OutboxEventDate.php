<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

abstract readonly class OutboxEventDate implements \JsonSerializable, \Stringable
{
    public static function none(): self
    {
        return new EmptyOutboxEventDate();
    }

    public static function fromDateTime(\DateTimeImmutable $value): self
    {
        return new KnownOutboxEventDate(value: $value);
    }

    abstract public function isEmpty(): bool;

    abstract public function equals(self $other): bool;
}
