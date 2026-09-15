<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\User\Public\Dto\UserProfileDto;

/**
 * Автор записи/комментария в read-model: снимок публичного профиля (id, имя и аватар с
 * преобразованиями одним значением). Аватар — публичное медиа модуля Media (оригинал + конверсии) или
 * null, если аватара нет: сервер не подставляет заглушку, дефолт ставит клиент.
 */
final readonly class AuthorView
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaDto|null $avatar,
    ) {}

    public static function fromProfile(UserProfileDto $profile): self
    {
        return new self(
            userId: $profile->userId,
            name: $profile->name,
            avatar: $profile->avatar,
        );
    }
}
