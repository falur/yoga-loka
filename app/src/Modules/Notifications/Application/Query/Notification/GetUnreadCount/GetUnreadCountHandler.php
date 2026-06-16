<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\GetUnreadCount;

use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetUnreadCountHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {}

    public function handle(GetUnreadCountQuery $query): UnreadCountResult
    {
        return new UnreadCountResult(
            count: $this->notificationRepository->countUnreadForRecipient(UserId::fromString($query->userId)),
        );
    }
}
