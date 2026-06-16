<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Cycle;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Typecast для nullable datetime-колонки consumed_at ↔ null-object Consumption: NULL в БД
 * становится Consumption::notConsumed(), момент — Consumption::at(). Конвенция превратила бы
 * NULL в null и сломала типизированное не-nullable свойство сущности.
 */
final class ConsumptionTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): Consumption
    {
        if ($value === null) {
            return Consumption::notConsumed();
        }

        if ($value instanceof \DateTimeImmutable) {
            return Consumption::at($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return Consumption::at(\DateTimeImmutable::createFromInterface($value));
        }

        return Consumption::at(new \DateTimeImmutable($value));
    }

    public static function uncastValue(Consumption $value): \DateTimeImmutable|null
    {
        return $value->value();
    }
}
