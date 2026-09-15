<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;

final readonly class PublishRealtimeNotificationCommand
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
