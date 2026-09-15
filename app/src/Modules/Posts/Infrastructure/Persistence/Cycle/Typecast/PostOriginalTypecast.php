<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class PostOriginalTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): PostOriginal
    {
        if ($value === null) {
            return PostOriginal::none();
        }

        return PostOriginal::pointingTo($value);
    }

    public static function uncastValue(PostOriginal|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
