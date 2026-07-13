<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Message;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Лёгкий триггер уведомления на получателя, который NotificationSender стейджит в транзакции
 * источника. Payload — только примитивы (createdAt — строка ISO-8601, action — вложенный
 * nullable DTO, actor — снимок автора {id, name, avatarMediaId} либо null), чтобы
 * ValinorOutboxMessageSerializer восстановил сообщение без кастомных фабрик. После commit-а
 * источника relay запускает DispatchNotificationJob.
 */
final readonly class NotificationRequested implements OutboxMessage
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
