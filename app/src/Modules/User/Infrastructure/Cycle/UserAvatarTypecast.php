<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Cycle;

use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class UserAvatarTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserAvatar
    {
        if ($value === null) {
            return UserAvatar::none();
        }

        return UserAvatar::pointingTo($value);
    }

    public static function uncastValue(UserAvatar|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
