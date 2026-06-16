<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Cycle;

use App\Modules\Auth\Domain\ValueObject\UserAgent;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Typecast для nullable text-колонки user_agent ↔ non-null VO UserAgent: NULL в БД становится
 * UnknownUserAgent, непустая строка — KnownUserAgent. Конвенция ValueObjectCast превратила бы
 * NULL в null и сломала типизированное не-nullable свойство сущности. ValueObjectCast зовёт
 * uncastValue() уже с объектом-VO, а castDatabaseValue() для text-колонки получает string|null.
 */
final class UserAgentTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserAgent
    {
        return UserAgent::fromNullable($value);
    }

    public static function uncastValue(UserAgent $value): string|null
    {
        return $value->toNullableString();
    }
}
