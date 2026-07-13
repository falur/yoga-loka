<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Notifications\Application\View\NotificationViewAssembler;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Cursor-пагинация списка инбокса. Запрашиваем limit+1, чтобы понять, есть ли следующая страница:
 * если строк больше limit — отдаём первые limit и nextCursor = id последней отданной. Снимки авторов
 * обогащаются актуальными аватарами (MediaView) через NotificationViewAssembler одним пакетным
 * запросом, без N+1.
 */
final readonly class ListNotificationsHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationViewAssembler $notificationViewAssembler,
    ) {}

    public function handle(ListNotificationsQuery $query): ListNotificationsResult
    {
        $cursor = $query->cursor !== null ? NotificationId::fromString($query->cursor) : null;
        $slice = CursorSlice::fromOverfetched(
            overfetched: $this->notificationRepository->findPageForRecipient(
                userId: UserId::fromString($query->userId),
                cursor: $cursor,
                limit: $query->limit + 1,
            ),
            limit: $query->limit,
            cursorOf: static fn(Notification $notification): string => $notification->id->value(),
        );

        return new ListNotificationsResult(
            notifications: $this->notificationViewAssembler->fromNotifications($slice->items),
            nextCursor: $slice->nextCursor,
        );
    }
}
