<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Event;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Запрос на realtime-доставку (Centrifugo), который фоновая рассылка стейджит, когда канал realtime
 * включён. Payload — только примитивы (action — nullable DTO, actor — снимок автора либо null).
 * После commit-а relay запускает PublishRealtimeNotificationJob.
 */
final readonly class NotificationRealtimeRequestedEvent implements IntegrationEvent
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
