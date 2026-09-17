<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Result;

use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;

/**
 * Ответ API об одном уведомлении инбокса. title/body — как сохранено (без перевода), action —
 * переход либо null, actor — снимок автора с аватаром-MediaDto либо null. Собирается
 * ListNotificationsHandler (страница инбокса) и MarkNotificationReadHandler (одно уведомление) из
 * готового набора аватаров (MediaDtoCollection), который каждый handler резолвит сам через публичный
 * контракт Media.
 */
final readonly class NotificationResult
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionResult|null $action,
        public NotificationActorResult|null $actor,
        public bool $read,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromNotification(Notification $notification, MediaDtoCollection $avatars): self
    {
        $action = $notification->action();

        return new self(
            id: $notification->id->value(),
            type: $notification->type->value(),
            title: $notification->title->value(),
            body: $notification->body->value(),
            action: $action->hasLink()
                ? new NotificationActionResult(
                    actionType: $action->actionType()->presentValue(),
                    actionId: $action->actionId()->presentValue(),
                )
                : null,
            actor: $notification->actor->isPresent()
                ? self::actorResult(actor: $notification->actor, avatars: $avatars)
                : null,
            read: $notification->isRead(),
            createdAt: $notification->createdAt,
        );
    }

    private static function actorResult(NotificationActor $actor, MediaDtoCollection $avatars): NotificationActorResult
    {
        return new NotificationActorResult(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatar: self::avatar(avatarMediaId: $actor->presentAvatarMediaId(), avatars: $avatars),
        );
    }

    private static function avatar(string|null $avatarMediaId, MediaDtoCollection $avatars): MediaDto|null
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
