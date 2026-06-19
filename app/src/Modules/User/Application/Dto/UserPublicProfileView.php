<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

/**
 * Публичный профиль пользователя для других модулей: идентификатор, отображаемое имя, всегда
 * непустая ссылка на аватар (реальная или по умолчанию) и код локали пользователя. Entity наружу
 * не отдаём, чтобы держать границу модуля.
 */
final readonly class UserPublicProfileView
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $avatarUrl,
        public string $locale,
    ) {}
}
