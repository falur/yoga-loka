<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Cycle;

use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Typecast для NOT NULL datetime-колонки expires_at: конвенция ValueObjectCast не выражает
 * восстановление DateTime в доменный VO Expiration (нужна фабрика fromDateTime).
 */
final class ExpirationTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|\DateTimeInterface $value): Expiration
    {
        if ($value instanceof \DateTimeImmutable) {
            return Expiration::fromDateTime($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return Expiration::fromDateTime(\DateTimeImmutable::createFromInterface($value));
        }

        return Expiration::fromDateTime(new \DateTimeImmutable($value));
    }

    public static function uncastValue(Expiration $value): \DateTimeImmutable
    {
        return $value->value();
    }
}
