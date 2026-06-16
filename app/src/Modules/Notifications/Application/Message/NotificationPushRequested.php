<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Message;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Запрос на push-доставку, который фоновая рассылка стейджит, когда канал push включён. Payload —
 * только примитивы (createdAt — строка, action — nullable DTO, actor — снимок автора либо null).
 * После commit-а relay запускает SendPushNotificationJob (фаза 6).
 */
final readonly class NotificationPushRequested implements OutboxMessage
{
    public function __construct(
        public string $userId,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionPayload|null $action,
        public NotificationActorPayload|null $actor,
        public string $createdAt,
    ) {}
}
