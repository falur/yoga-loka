<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Cycle;

use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class OutboxLastErrorTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|null $value,
    ): OutboxLastError {
        // NULL и пустая строка трактуются одинаково — как «ошибки нет». Так гидрация
        // устойчива к ручным правкам и импортам, где в колонку попала пустая строка,
        // а трактовка пустого значения совпадает с парсером сырых строк.
        if ($value === null || \trim($value) === '') {
            return OutboxLastError::none();
        }

        return OutboxLastError::fromString($value);
    }

    public static function uncastValue(
        OutboxLastError|null $value,
    ): string|null {
        return $value?->toDatabaseValue();
    }
}
