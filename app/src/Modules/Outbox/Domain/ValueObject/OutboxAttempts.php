<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class OutboxAttempts extends AbstractRangedIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = \PHP_INT_MAX;
    protected const string NAME = 'Количество попыток outbox';

    public static function zero(): self
    {
        return self::fromInt(0);
    }

    public function canIncrement(OutboxMaxAttempts $outboxMaxAttempts): bool
    {
        return $this->value() < $outboxMaxAttempts->value();
    }

    public function isLastAllowed(OutboxMaxAttempts $outboxMaxAttempts): bool
    {
        return $this->value() >= $outboxMaxAttempts->value() - 1;
    }

    public function increment(): self
    {
        if ($this->value() >= self::MAX) {
            throw new InvalidDomainValueException('Количество попыток outbox превышено.');
        }

        return self::fromInt($this->value() + 1);
    }
}
