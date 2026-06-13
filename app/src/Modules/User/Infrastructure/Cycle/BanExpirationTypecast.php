<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Cycle;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class BanExpirationTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface|null $value): BanExpiration
    {
        if ($value === null) {
            return BanExpiration::permanent();
        }

        if ($value instanceof \DateTimeImmutable) {
            return BanExpiration::until($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return BanExpiration::until(\DateTimeImmutable::createFromInterface($value));
        }

        return BanExpiration::until(new \DateTimeImmutable($value));
    }

    public static function uncastValue(BanExpiration|null $value): \DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
