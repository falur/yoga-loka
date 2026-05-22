<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

interface ColumnValueTypecast
{
    public static function castDatabaseValue(
        bool|int|float|string|\DateTimeInterface|null $value,
    ): object|null;

    public static function uncastValue(
        object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null;
}
