<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class BanUnbannedReasonTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): BanUnbannedReason
    {
        if ($value === null) {
            return BanUnbannedReason::none();
        }

        return BanUnbannedReason::of($value);
    }

    public static function uncastValue(BanUnbannedReason|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
