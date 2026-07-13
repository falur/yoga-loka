<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\AuthorView;
use App\Shared\Presentation\Http\Resource\AbstractResource;
use App\Shared\Presentation\Http\Resource\MediaResource;

/**
 * Автор записи/комментария в ответе API: идентификатор, имя и аватар с преобразованиями (оригинал +
 * конверсии) одним объектом или null, если аватара нет — заглушку рисует клиент. Клиент сам выбирает
 * профиль показа; потребители одной ссылки берут avatar.original.url.
 */
final readonly class AuthorResource extends AbstractResource
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaResource|null $avatar,
    ) {}

    public static function fromView(AuthorView $author): self
    {
        return new self(
            userId: $author->userId,
            name: $author->name,
            avatar: $author->avatar === null ? null : MediaResource::fromView($author->avatar),
        );
    }
}
