<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Ресурс уведомления для мобильного клиента. title/body отдаются как сохранено (без перевода),
 * action — вложенный ресурс перехода либо null, actor — вложенный снимок автора {id, name, avatarUrl}
 * либо null (клиент показывает аватар автора без отдельного запроса к профилю).
 */
final readonly class NotificationResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionResource|null $action,
        public NotificationActorResource|null $actor,
        public bool $read,
        public string $createdAt,
    ) {}

    public static function fromEntity(Notification $notification): self
    {
        $action = $notification->action();

        return new self(
            id: $notification->id->value(),
            type: $notification->type->value(),
            title: $notification->title->value(),
            body: $notification->body->value(),
            action: $action->hasLink() ? NotificationActionResource::fromAction($action) : null,
            actor: $notification->actor->isPresent() ? NotificationActorResource::fromActor($notification->actor) : null,
            read: $notification->isRead(),
            createdAt: $notification->createdAt->format(\DateTimeInterface::ATOM),
        );
    }
}
