<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Event;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Лёгкий триггер уведомления на получателя, который сценарий RequestNotification стейджит в транзакции
 * источника. Payload — только примитивы (createdAt — строка ISO-8601, action — вложенный
 * nullable DTO, actor — снимок автора {id, name, avatarMediaId} либо null), чтобы
 * ValinorOutboxMessageSerializer восстановил событие без кастомных фабрик. После commit-а
 * источника relay запускает DispatchNotificationJob.
 */
final readonly class NotificationRequestedEvent implements IntegrationEvent
{
    public function __construct(
        public string $userId,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionDto|null $action,
        public NotificationActorDto|null $actor,
        public string $createdAt,
    ) {}
}
