<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Cycle;

use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class UserLocationTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserLocation
    {
        if ($value === null) {
            return UserLocation::none();
        }

        return UserLocation::fromString($value);
    }

    public static function uncastValue(UserLocation|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
