<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class UserSpiritualNameTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserSpiritualName
    {
        if ($value === null) {
            return UserSpiritualName::none();
        }

        return UserSpiritualName::fromString($value);
    }

    public static function uncastValue(UserSpiritualName|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
