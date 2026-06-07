<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Cycle;

use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class OutboxAvailableAtTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|\DateTimeInterface $value,
    ): OutboxAvailableAt {
        if ($value instanceof \DateTimeImmutable) {
            return OutboxAvailableAt::fromDateTime($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return OutboxAvailableAt::fromDateTime(\DateTimeImmutable::createFromInterface($value));
        }

        return OutboxAvailableAt::fromDateTime(new \DateTimeImmutable($value));
    }

    public static function uncastValue(
        OutboxAvailableAt $value,
    ): \DateTimeImmutable {
        return $value->value();
    }
}
