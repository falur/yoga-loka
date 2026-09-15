<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class UserDeletionTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): UserDeletion
    {
        if ($value === null) {
            return UserDeletion::active();
        }

        if ($value instanceof \DateTimeImmutable) {
            return UserDeletion::at($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return UserDeletion::at(\DateTimeImmutable::createFromInterface($value));
        }

        return UserDeletion::at(new \DateTimeImmutable($value));
    }

    public static function uncastValue(UserDeletion|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
