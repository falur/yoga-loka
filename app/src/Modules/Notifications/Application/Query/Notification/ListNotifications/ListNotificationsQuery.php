<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

final readonly class ListNotificationsQuery
{
    public function __construct(
        public string $userId,
        public string|null $cursor,
        public int $limit,
    ) {}
}
