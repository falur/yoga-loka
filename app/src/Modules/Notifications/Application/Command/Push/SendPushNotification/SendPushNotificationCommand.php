<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Push\SendPushNotification;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;

final readonly class SendPushNotificationCommand
{
    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public NotificationActionDto|null $action,
        public NotificationActorDto|null $actor,
    ) {}
}
