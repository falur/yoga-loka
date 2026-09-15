<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class CommentDeletionReasonTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): CommentDeletionReason
    {
        if ($value === null) {
            return CommentDeletionReason::none();
        }

        return CommentDeletionReason::of($value);
    }

    public static function uncastValue(CommentDeletionReason|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
