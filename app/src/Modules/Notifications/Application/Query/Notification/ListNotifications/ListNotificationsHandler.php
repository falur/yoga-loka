<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Cursor-пагинация списка инбокса. Запрашиваем limit+1, чтобы понять, есть ли следующая страница:
 * если строк больше limit — отдаём первые limit и nextCursor = id последней отданной.
 */
final readonly class ListNotificationsHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {}

    public function handle(ListNotificationsQuery $query): ListNotificationsResult
    {
        $cursor = $query->cursor !== null ? NotificationId::fromString($query->cursor) : null;
        $page = $this->notificationRepository->findPageForRecipient(
            userId: UserId::fromString($query->userId),
            cursor: $cursor,
            limit: $query->limit + 1,
        )->all();

        if (\count($page) <= $query->limit) {
            return new ListNotificationsResult(
                notifications: new NotificationCollection($page),
                nextCursor: null,
            );
        }

        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);

        return new ListNotificationsResult(
            notifications: new NotificationCollection($visible),
            nextCursor: $visible[\count($visible) - 1]->id->value(),
        );
    }
}
