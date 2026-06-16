<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Вложенный ресурс автора-инициатора (снимок профиля). Отдельный DTO, а не inline array-shape, чтобы
 * OpenAPI-парсер не схлопнул union. Несёт id, имя и ссылку на аватар — клиент показывает аватар без
 * отдельного запроса к профилю. Создаётся только когда автор есть.
 */
final readonly class NotificationActorResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $avatarUrl,
    ) {}

    public static function fromActor(NotificationActor $actor): self
    {
        return new self(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatarUrl: $actor->presentAvatarUrl(),
        );
    }
}
