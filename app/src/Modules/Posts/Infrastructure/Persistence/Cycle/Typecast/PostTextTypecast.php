<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class PostTextTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): PostText
    {
        if ($value === null) {
            return PostText::none();
        }

        return PostText::fromString($value);
    }

    public static function uncastValue(PostText|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
