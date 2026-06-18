<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Cycle;

use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class BlockUnblockedReasonTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): BlockUnblockedReason
    {
        if ($value === null) {
            return BlockUnblockedReason::none();
        }

        return BlockUnblockedReason::of($value);
    }

    public static function uncastValue(BlockUnblockedReason|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
