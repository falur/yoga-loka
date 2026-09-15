<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

use App\Modules\Media\Public\Dto\MediaDto;

/**
 * Автор-инициатор уведомления в read-model: id, имя и аватар одним публичным медиа (оригинал + все
 * конверсии) либо null, если аватара нет или его медиа недоступно. Аватар собирается на чтении через
 * публичный контракт Media из сохранённого id медиа — та же форма, что у аватара в профиле
 * пользователя, поэтому клиент показывает одно и то же значение и не завязан на строку-ссылку.
 * Заглушку рисует клиент.
 */
final readonly class NotificationActorView
{
    public function __construct(
        public string $id,
        public string $name,
        public MediaDto|null $avatar,
    ) {}
}
