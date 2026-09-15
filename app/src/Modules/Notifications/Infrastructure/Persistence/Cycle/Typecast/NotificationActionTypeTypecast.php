<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

/**
 * Гидрация колонки action_type (nullable string) в null-object NotificationActionType:
 * NULL -> none(), непустая строка -> of(). Образец — MediaProcessingErrorTypecast.
 */
final class NotificationActionTypeTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|null $value,
    ): NotificationActionType {
        if ($value === null) {
            return NotificationActionType::none();
        }

        return NotificationActionType::of($value);
    }

    public static function uncastValue(
        NotificationActionType|null $value,
    ): string|null {
        return $value?->value();
    }
}
