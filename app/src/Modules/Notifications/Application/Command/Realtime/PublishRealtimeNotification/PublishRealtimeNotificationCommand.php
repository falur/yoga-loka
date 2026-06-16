<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;

final readonly class PublishRealtimeNotificationCommand
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
