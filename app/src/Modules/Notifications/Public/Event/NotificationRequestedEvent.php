<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Event;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use GianTiaga\SpiralOutbox\IntegrationEventContract;

/**
 * Лёгкий триггер уведомления на получателя, который сценарий RequestNotification стейджит в транзакции
 * источника. Payload — только примитивы (createdAt — строка ISO-8601, action — вложенный
 * nullable DTO, actor — снимок автора {id, name, avatarMediaId} либо null), чтобы
 * сериализатор пакета outbox восстановил событие без кастомных фабрик. После commit-а
 * источника relay запускает DispatchNotificationJob.
 */
final readonly class NotificationRequestedEvent implements IntegrationEventContract
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
