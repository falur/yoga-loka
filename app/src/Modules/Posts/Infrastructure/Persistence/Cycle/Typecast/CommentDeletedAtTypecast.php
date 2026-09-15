<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class CommentDeletedAtTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): CommentDeletedAt
    {
        if ($value === null) {
            return CommentDeletedAt::notDeleted();
        }

        if ($value instanceof \DateTimeImmutable) {
            return CommentDeletedAt::at($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return CommentDeletedAt::at(\DateTimeImmutable::createFromInterface($value));
        }

        return CommentDeletedAt::at(new \DateTimeImmutable($value));
    }

    public static function uncastValue(CommentDeletedAt|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
