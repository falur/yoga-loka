<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Порт единственной массовой записи проекта: отметка всех непрочитанных уведомлений получателя
 * прочитанными. Порт назван своей операцией и остаётся отдельным от NotificationRepository,
 * потому что операция не проходит через доменный переход уведомления и её набор не ограничен
 * сверху.
 */
interface MarkAllNotificationsReadContract
{
    /**
     * Массово отмечает все непрочитанные уведомления получателя прочитанными одним UPDATE.
     * Возвращает число затронутых строк.
     */
    public function markAllReadForRecipient(UserId $userId, \DateTimeImmutable $readAt): int;
}
