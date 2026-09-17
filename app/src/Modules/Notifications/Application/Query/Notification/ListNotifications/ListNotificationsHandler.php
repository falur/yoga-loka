<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Notification\ListNotifications;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Notifications\Application\Result\NotificationResult;
use App\Modules\Notifications\Application\Result\NotificationResultCollection;
use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Cursor-пагинация списка инбокса. Запрашиваем limit+1, чтобы понять, есть ли следующая страница:
 * если строк больше limit — отдаём первые limit и nextCursor = id последней отданной. Снимки авторов
 * обогащаются актуальными аватарами (MediaDto) через публичный контракт Media одним пакетным
 * запросом на страницу, без N+1.
 */
final readonly class ListNotificationsHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MediaContract $media,
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

        $avatars = $this->resolveAvatars($slice->items);

        return new ListNotificationsResult(
            notifications: new NotificationResultCollection(
                $slice->items->toBase()->map(
                    fn(Notification $notification): NotificationResult
                        => NotificationResult::fromNotification(notification: $notification, avatars: $avatars),
                ),
            ),
            nextCursor: $slice->nextCursor,
        );
    }

    private function resolveAvatars(NotificationCollection $notifications): MediaDtoCollection
    {
        $mediaIds = $this->avatarMediaIds($notifications);

        if ($mediaIds === []) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds($mediaIds);
    }

    /**
     * @return list<string>
     */
    private function avatarMediaIds(NotificationCollection $notifications): array
    {
        return \array_values(\array_unique(
            $notifications->toBase()
                ->filter(static fn(Notification $notification): bool => $notification->actor->isPresent()
                    && $notification->actor->presentAvatarMediaId() !== null)
                ->map(static fn(Notification $notification): string => (string) $notification->actor->presentAvatarMediaId())
                ->all(),
        ));
    }
}
