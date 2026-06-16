<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Push\SendPushNotification;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;

final readonly class SendPushNotificationCommand
{
    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public NotificationActionPayload|null $action,
        public NotificationActorPayload|null $actor,
    ) {}
}
