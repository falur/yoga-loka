<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Cycle;

use App\Modules\Auth\Domain\ValueObject\Ip;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Typecast для nullable string-колонки ip ↔ non-null VO Ip: NULL в БД становится UnknownIp,
 * валидная строка — KnownIp. Конвенция ValueObjectCast превратила бы NULL в null и сломала
 * типизированное не-nullable свойство сущности. ValueObjectCast зовёт uncastValue() уже с
 * объектом-VO, а castDatabaseValue() для string-колонки получает string|null.
 */
final class IpTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): Ip
    {
        return Ip::fromNullable($value);
    }

    public static function uncastValue(Ip $value): string|null
    {
        return $value->toNullableString();
    }
}
