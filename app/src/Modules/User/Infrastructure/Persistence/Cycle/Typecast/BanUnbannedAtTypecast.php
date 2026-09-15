<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class BanUnbannedAtTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): BanUnbannedAt
    {
        if ($value === null) {
            return BanUnbannedAt::notUnbanned();
        }

        if ($value instanceof \DateTimeImmutable) {
            return BanUnbannedAt::at($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return BanUnbannedAt::at(\DateTimeImmutable::createFromInterface($value));
        }

        return BanUnbannedAt::at(new \DateTimeImmutable($value));
    }

    public static function uncastValue(BanUnbannedAt|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
