<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Enum;

/**
 * Публичный дубликат доменного NotificationChannel: домен не вправе зависеть от Public, поэтому
 * набор вариантов и их строковые значения повторяются здесь и держатся в синхронном состоянии
 * unit-проверкой совпадения. Преобразование публичного варианта в доменный — по строковому значению.
 *
 * Канал доставки уведомления: инбокс (in-app список), push (FCM), realtime (Centrifugo).
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Push = 'push';
    case Realtime = 'realtime';
}
