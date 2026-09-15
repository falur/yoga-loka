<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class BlockUnblockedByTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): BlockUnblockedBy
    {
        if ($value === null) {
            return BlockUnblockedBy::none();
        }

        return BlockUnblockedBy::by(UserId::fromString($value));
    }

    public static function uncastValue(BlockUnblockedBy|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
