<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Application\View\NotificationActorView;
use App\Shared\Presentation\Http\Resource\AbstractResource;
use App\Shared\Presentation\Http\Resource\MediaResource;

/**
 * Вложенный ресурс автора-инициатора (снимок профиля). Отдельный DTO, а не inline array-shape, чтобы
 * OpenAPI-парсер не схлопнул union. Несёт id, имя и аватар одним MediaView (оригинал + конверсии) либо
 * null, если аватара нет — клиент показывает аватар без отдельного запроса к профилю. Создаётся только
 * когда автор есть.
 */
final readonly class NotificationActorResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $name,
        public MediaResource|null $avatar,
    ) {}

    public static function fromView(NotificationActorView $actor): self
    {
        return new self(
            id: $actor->id,
            name: $actor->name,
            avatar: $actor->avatar === null ? null : MediaResource::fromView($actor->avatar),
        );
    }
}
