<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;

final readonly class ListNotificationsResult
{
    public function __construct(
        public NotificationCollection $notifications,
        public string|null $nextCursor,
    ) {}
}
