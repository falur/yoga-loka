<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\GetUnreadCount;

final readonly class UnreadCountResult
{
    public function __construct(
        public int $count,
    ) {}
}
