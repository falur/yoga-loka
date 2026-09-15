<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\UserBio;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class UserBioTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserBio
    {
        if ($value === null) {
            return UserBio::none();
        }

        return UserBio::fromString($value);
    }

    public static function uncastValue(UserBio|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
