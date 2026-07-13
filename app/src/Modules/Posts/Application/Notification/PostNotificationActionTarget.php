<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

/**
 * Цель deep-link уведомления модуля Posts: на что ведёт переход по тапу. Закрытый набор вместо
 * магической строки — значение уходит в NotificationAction как код перехода.
 */
enum PostNotificationActionTarget: string
{
    case Post = 'post';
    case Comment = 'comment';
}
