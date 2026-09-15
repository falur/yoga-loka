<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class CommentDeletedByTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): CommentDeletedBy
    {
        if ($value === null) {
            return CommentDeletedBy::none();
        }

        return CommentDeletedBy::by(UserId::fromString($value));
    }

    public static function uncastValue(CommentDeletedBy|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
