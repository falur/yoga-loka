<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\Media\Application\View\MediaView;

/**
 * Автор записи/комментария в read-model: снимок публичного профиля (id, имя и аватар с
 * преобразованиями одним значением). Аватар — общий MediaView (оригинал + конверсии) или null, если
 * аватара нет: сервер не подставляет заглушку, дефолт ставит клиент.
 */
final readonly class AuthorView
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaView|null $avatar,
    ) {}

    public static function fromProfile(UserPublicProfileView $profile): self
    {
        return new self(
            userId: $profile->userId,
            name: $profile->name,
            avatar: $profile->avatar,
        );
    }
}
