<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

use App\Modules\Media\Application\Dto\MediaUrlsResultCollection;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsQuery;
use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Shared\Application\View\MediaView;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает read-model уведомлений, обогащая снимок автора актуальным аватаром через модуль Media.
 * Снимок хранит только id медиа-аватара, поэтому полный MediaView (оригинал + конверсии) резолвится
 * здесь на чтении — так ссылка всегда валидна (у private-медиа presigned-ссылки временные) и не
 * выдумывается сервером. Аватары всего набора уведомлений резолвятся одним пакетным FindMediaUrls,
 * чтобы на странице инбокса не было N+1.
 *
 * Межмодульный Query идёт через QueryBus: шина возвращает ровно тип Handler::handle()
 * (MediaUrlsResultCollection), поэтому контракт «медиа недоступно -> аватара нет» сохраняется без
 * try-catch, а middleware обработчика (в том числе #[LogOperation]) работает.
 */
final readonly class NotificationViewAssembler
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private FindMediaUrlsHandler $findMediaUrlsHandler,
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

    private function resolveAvatars(NotificationCollection $notifications): MediaUrlsResultCollection
    {
        $mediaIds = $this->avatarMediaIds($notifications);

        if ($mediaIds === []) {
            return new MediaUrlsResultCollection();
        }

        return $this->queryBus->dispatch(
            query: new FindMediaUrlsQuery(mediaIds: $mediaIds),
            handler: $this->findMediaUrlsHandler->handle(...),
        );
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

    private function view(Notification $notification, MediaUrlsResultCollection $avatars): NotificationView
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

    private function actorView(NotificationActor $actor, MediaUrlsResultCollection $avatars): NotificationActorView
    {
        return new NotificationActorView(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatar: $this->avatar(avatarMediaId: $actor->presentAvatarMediaId(), avatars: $avatars),
        );
    }

    private function avatar(string|null $avatarMediaId, MediaUrlsResultCollection $avatars): MediaView|null
    {
        if ($avatarMediaId === null) {
            return null;
        }

        $mediaUrls = $avatars->get($avatarMediaId);

        // Аватара нет, если медиа недоступно или оригинал удалён: отдаём null «всё или ничего», без
        // конверсий удалённого оригинала. Тот же контракт, что у аватара в профиле (User).
        if ($mediaUrls === null || $mediaUrls->original === null) {
            return null;
        }

        return $mediaUrls->toView(id: $avatarMediaId, position: null);
    }
}
