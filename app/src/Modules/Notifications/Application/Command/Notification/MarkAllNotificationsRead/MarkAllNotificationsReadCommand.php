<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead;

final readonly class MarkAllNotificationsReadCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
