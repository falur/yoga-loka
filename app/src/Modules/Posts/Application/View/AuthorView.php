<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

/**
 * Автор записи/комментария в read-model: снимок публичного профиля (id, имя, ссылка на аватар).
 */
final readonly class AuthorView
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $avatarUrl,
    ) {}
}
