<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Cycle;

use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class BanUnbannedByTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): BanUnbannedBy
    {
        if ($value === null) {
            return BanUnbannedBy::none();
        }

        return BanUnbannedBy::by(UserId::fromString($value));
    }

    public static function uncastValue(BanUnbannedBy|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
