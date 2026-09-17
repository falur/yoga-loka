<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\GetUnreadCount;

use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetUnreadCountHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {}

    public function handle(GetUnreadCountQuery $query): GetUnreadCountResult
    {
        return new GetUnreadCountResult(
            count: $this->notificationRepository->countUnreadForRecipient(UserId::fromString($query->userId)),
        );
    }
}
