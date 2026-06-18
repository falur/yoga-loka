<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Cycle;

use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class CommentParentTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): CommentParent
    {
        if ($value === null) {
            return CommentParent::none();
        }

        return CommentParent::pointingTo($value);
    }

    public static function uncastValue(CommentParent|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
