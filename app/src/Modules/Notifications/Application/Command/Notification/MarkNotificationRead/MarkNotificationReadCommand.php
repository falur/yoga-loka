<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead;

final readonly class MarkNotificationReadCommand
{
    public function __construct(
        public string $userId,
        public string $notificationId,
    ) {}
}
