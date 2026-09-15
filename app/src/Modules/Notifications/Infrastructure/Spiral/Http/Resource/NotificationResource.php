<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Resource;

use App\Modules\Notifications\Application\View\NotificationView;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

/**
 * Ресурс уведомления для мобильного клиента. title/body отдаются как сохранено (без перевода),
 * action — вложенный ресурс перехода либо null, actor — вложенный снимок автора с аватаром-MediaView
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
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromView(NotificationView $notification): self
    {
        return new self(
            id: $notification->id,
            type: $notification->type,
            title: $notification->title,
            body: $notification->body,
            action: $notification->action === null ? null : NotificationActionResource::fromView($notification->action),
            actor: $notification->actor === null ? null : NotificationActorResource::fromView($notification->actor),
            read: $notification->read,
            createdAt: $notification->createdAt,
        );
    }
}
