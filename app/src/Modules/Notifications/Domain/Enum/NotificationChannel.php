<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Enum;

/**
 * Канал доставки уведомления: инбокс (in-app список), push (FCM), realtime (Centrifugo).
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Push = 'push';
    case Realtime = 'realtime';
}
