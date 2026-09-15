<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;

/**
 * Собирает read-model уведомлений, обогащая снимок автора актуальным аватаром через публичный
 * контракт Media. Снимок хранит только id медиа-аватара, поэтому полное медиа (оригинал + конверсии)
 * резолвится здесь на чтении — так ссылка всегда валидна (у private-медиа presigned-ссылки временные)
 * и не выдумывается сервером. Аватары всего набора уведомлений резолвятся одним пакетным вызовом,
 * чтобы на странице инбокса не было N+1; при пустом наборе идентификаторов к соседу не ходим.
 *
 * Контракт возвращает набор без недоступных медиа, поэтому правило «медиа недоступно -> аватара нет»
 * выражается отсутствием идентификатора в наборе и не требует try-catch.
 */
final readonly class NotificationViewAssembler
{
    public function __construct(
        private MediaContract $media,
    ) {}

    public function fromNotifications(NotificationCollection $notifications): NotificationViewCollection
    {
        $avatars = $this->resolveAvatars($notifications);

        return new NotificationViewCollection(
            $notifications->toBase()->map(
                fn(Notification $notification): NotificationView => $this->view(notification: $notification, avatars: $avatars),
            ),
        );
    }

    public function fromNotification(Notification $notification): NotificationView
    {
        return $this->view(
            notification: $notification,
            avatars: $this->resolveAvatars(new NotificationCollection([$notification])),
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

    private function view(Notification $notification, MediaDtoCollection $avatars): NotificationView
    {
        $action = $notification->action();

        return new NotificationView(
            id: $notification->id->value(),
            type: $notification->type->value(),
            title: $notification->title->value(),
            body: $notification->body->value(),
            action: $action->hasLink()
                ? new NotificationActionView(
                    actionType: $action->actionType()->presentValue(),
                    actionId: $action->actionId()->presentValue(),
                )
                : null,
            actor: $notification->actor->isPresent()
                ? $this->actorView(actor: $notification->actor, avatars: $avatars)
                : null,
            read: $notification->isRead(),
            createdAt: $notification->createdAt,
        );
    }

    private function actorView(NotificationActor $actor, MediaDtoCollection $avatars): NotificationActorView
    {
        return new NotificationActorView(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatar: $this->avatar(avatarMediaId: $actor->presentAvatarMediaId(), avatars: $avatars),
        );
    }

    private function avatar(string|null $avatarMediaId, MediaDtoCollection $avatars): MediaDto|null
    {
        if ($avatarMediaId === null) {
            return null;
        }

        $media = $avatars->get($avatarMediaId);

        // Аватара нет, если медиа недоступно или оригинал удалён: отдаём null «всё или ничего», без
        // конверсий удалённого оригинала. Тот же контракт, что у аватара в профиле (User).
        if ($media === null || $media->original === null) {
            return null;
        }

        return $media;
    }
}
