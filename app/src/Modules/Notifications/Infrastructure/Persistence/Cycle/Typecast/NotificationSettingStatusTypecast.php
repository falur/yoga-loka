<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

/**
 * Гидрация boolean-колонки enabled в enum NotificationSettingStatus (Entity без примитивов,
 * rules.md:36). Общий ValueObjectCast тут не подходит: он ждёт строку/число для BackedEnum::from,
 * а булеву колонку PostgreSQL отдаёт по-разному в зависимости от драйвера (bool/0-1/'t'-'f'),
 * поэтому нормализуем вход явно.
 */
final class NotificationSettingStatusTypecast implements ColumnValueTypecast
{
    /**
     * Колонка enabled объявлена not null, поэтому штатно null оттуда не приходит. null в сигнатуре
     * оставлен как защитный fallback: если драйвер/гидратор отдаст null (например при join без строки),
     * трактуем его как Disabled, а не падаем типизацией — статус настройки лучше «выключен», чем сбой.
     */
    public static function castDatabaseValue(
        bool|int|string|null $value,
    ): NotificationSettingStatus {
        return self::isEnabled($value)
            ? NotificationSettingStatus::Enabled
            : NotificationSettingStatus::Disabled;
    }

    public static function uncastValue(
        NotificationSettingStatus|null $value,
    ): bool {
        return $value === NotificationSettingStatus::Enabled;
    }

    private static function isEnabled(bool|int|string|null $value): bool
    {
        return match (true) {
            \is_bool($value) => $value,
            \is_int($value) => $value === 1,
            \is_string($value) => \in_array(needle: \strtolower($value), haystack: ['1', 't', 'true', 'y'], strict: true),
            default => false,
        };
    }
}
