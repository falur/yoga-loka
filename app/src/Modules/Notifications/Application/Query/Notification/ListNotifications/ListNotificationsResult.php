<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Notifications\Application\View\NotificationViewCollection;

final readonly class ListNotificationsResult
{
    public function __construct(
        public NotificationViewCollection $notifications,
        public string|null $nextCursor,
    ) {}
}
