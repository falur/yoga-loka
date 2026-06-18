<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Cycle;

use App\Modules\Posts\Domain\ValueObject\PostDeletion;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class PostDeletionTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): PostDeletion
    {
        if ($value === null) {
            return PostDeletion::notDeleted();
        }

        if ($value instanceof \DateTimeImmutable) {
            return PostDeletion::deletedAt($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return PostDeletion::deletedAt(\DateTimeImmutable::createFromInterface($value));
        }

        return PostDeletion::deletedAt(new \DateTimeImmutable($value));
    }

    public static function uncastValue(PostDeletion|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
