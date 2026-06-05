<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaExpirationTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|\DateTimeInterface|null $value,
    ): MediaExpiration {
        if ($value === null) {
            return MediaExpiration::permanent();
        }

        if ($value instanceof \DateTimeImmutable) {
            return MediaExpiration::temporaryUntil($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return MediaExpiration::temporaryUntil(\DateTimeImmutable::createFromInterface($value));
        }

        return MediaExpiration::temporaryUntil(new \DateTimeImmutable($value));
    }

    public static function uncastValue(
        MediaExpiration|null $value,
    ): \DateTimeImmutable|null {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
