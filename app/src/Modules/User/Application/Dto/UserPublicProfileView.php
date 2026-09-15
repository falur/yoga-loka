<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

use App\Modules\Media\Application\View\MediaView;
use App\Shared\Domain\Enum\Locale;

/**
 * Публичный профиль пользователя для других модулей: идентификатор, отображаемое имя, аватар с
 * преобразованиями одним значением и локаль пользователя (enum-ом — закрытый набор). Entity наружу не
 * отдаём, чтобы держать границу модуля.
 *
 * avatar — общий read-model MediaView (оригинал + конверсии) или null, если у пользователя нет
 * аватара либо его медиа недоступно: сервер не выдумывает ссылку-заглушку, дефолтный аватар ставит
 * клиент. Потребитель одной ссылки (уведомления, пуш) берёт avatar?->original?->url.
 */
final readonly class UserPublicProfileView
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaView|null $avatar,
        public Locale $locale,
    ) {}
}
