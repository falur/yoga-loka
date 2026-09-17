<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Dto;

use App\Modules\Media\Public\Dto\MediaDto;
use App\Shared\Domain\Enum\Locale;

/**
 * Публичный профиль пользователя для соседей: идентификатор, отображаемое имя, аватар с
 * преобразованиями одним значением и локаль пользователя (закрытый набор общим примитивом).
 *
 * avatar — публичное медиа модуля Media (оригинал + конверсии) или null, если у пользователя нет
 * аватара либо его медиа недоступно: сервер не выдумывает ссылку-заглушку, дефолтный аватар ставит
 * клиент. Потребитель одной ссылки (уведомления, пуш) берёт avatar?->original?->url.
 */
final readonly class UserProfileDto
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaDto|null $avatar,
        public Locale $locale,
    ) {}
}
