<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\DispatchNotification;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;

/**
 * Команда фоновой рассылки уведомления. Внешняя граница принимает примитивы: outboxId/createdAt —
 * строки, action — nullable payload-DTO, actor — снимок автора {id, name, avatarUrl} либо null.
 * Handler сам строит доменные VO.
 */
final readonly class DispatchNotificationCommand
{
    public function __construct(
        public string $outboxId,
        public string $userId,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionPayload|null $action,
        public NotificationActorPayload|null $actor,
        public string $createdAt,
    ) {}
}
