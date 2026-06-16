<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Cycle;

use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Гидрация колонки action_id (nullable string) в null-object NotificationActionId:
 * NULL -> none(), непустая строка -> of().
 */
final class NotificationActionIdTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|null $value,
    ): NotificationActionId {
        if ($value === null) {
            return NotificationActionId::none();
        }

        return NotificationActionId::of($value);
    }

    public static function uncastValue(
        NotificationActionId|null $value,
    ): string|null {
        return $value?->value();
    }
}
