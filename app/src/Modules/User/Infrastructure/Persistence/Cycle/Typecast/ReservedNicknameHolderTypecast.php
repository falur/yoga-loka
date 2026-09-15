<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class ReservedNicknameHolderTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): ReservedNicknameHolder
    {
        if ($value === null) {
            return ReservedNicknameHolder::unassigned();
        }

        return ReservedNicknameHolder::assignedTo(UserId::fromString($value));
    }

    public static function uncastValue(ReservedNicknameHolder|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
