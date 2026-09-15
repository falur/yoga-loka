<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

/**
 * Гидрация колонки read_at (nullable datetime) в null-object NotificationReadState:
 * NULL -> unread(), дата -> readAt(). Образец — MediaExpirationTypecast.
 */
final class NotificationReadStateTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|\DateTimeInterface|null $value,
    ): NotificationReadState {
        if ($value === null) {
            return NotificationReadState::unread();
        }

        if ($value instanceof \DateTimeImmutable) {
            return NotificationReadState::readAt($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return NotificationReadState::readAt(\DateTimeImmutable::createFromInterface($value));
        }

        return NotificationReadState::readAt(new \DateTimeImmutable($value));
    }

    public static function uncastValue(
        NotificationReadState|null $value,
    ): \DateTimeImmutable|null {
        return $value?->value();
    }
}
