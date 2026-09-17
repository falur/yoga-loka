<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Notifications\Application\Result\NotificationResultCollection;

final readonly class ListNotificationsResult
{
    public function __construct(
        public NotificationResultCollection $notifications,
        public string|null $nextCursor,
    ) {}
}
