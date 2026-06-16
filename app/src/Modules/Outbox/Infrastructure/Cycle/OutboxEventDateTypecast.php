<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Cycle;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class OutboxEventDateTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|\DateTimeInterface|null $value,
    ): OutboxEventDate {
        if ($value === null) {
            return OutboxEventDate::none();
        }

        if ($value instanceof \DateTimeImmutable) {
            return OutboxEventDate::fromDateTime($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return OutboxEventDate::fromDateTime(\DateTimeImmutable::createFromInterface($value));
        }

        return OutboxEventDate::fromDateTime(new \DateTimeImmutable($value));
    }

    public static function uncastValue(
        OutboxEventDate|null $value,
    ): \DateTimeImmutable|null {
        return $value?->value();
    }
}
