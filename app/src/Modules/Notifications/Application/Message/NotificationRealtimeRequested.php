<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Message;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Запрос на realtime-доставку (Centrifugo), который фоновая рассылка стейджит, когда канал realtime
 * включён. Payload — только примитивы (action — nullable DTO, actor — снимок автора либо null).
 * После commit-а relay запускает PublishRealtimeNotificationJob (фаза 6).
 */
final readonly class NotificationRealtimeRequested implements OutboxMessage
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
