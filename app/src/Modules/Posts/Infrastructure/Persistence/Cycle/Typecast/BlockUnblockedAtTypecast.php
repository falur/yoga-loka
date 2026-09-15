<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class BlockUnblockedAtTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): BlockUnblockedAt
    {
        if ($value === null) {
            return BlockUnblockedAt::notUnblocked();
        }

        if ($value instanceof \DateTimeImmutable) {
            return BlockUnblockedAt::at($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return BlockUnblockedAt::at(\DateTimeImmutable::createFromInterface($value));
        }

        return BlockUnblockedAt::at(new \DateTimeImmutable($value));
    }

    public static function uncastValue(BlockUnblockedAt|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
