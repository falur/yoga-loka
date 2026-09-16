<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Result;

use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Enum\Locale;

/**
 * Публичный профиль пользователя: идентификатор, отображаемое имя, аватар с преобразованиями одним
 * значением и локаль пользователя (enum-ом — закрытый набор). Entity наружу не отдаём, чтобы держать
 * границу модуля. Переиспользуется GetUserPublicProfileHandler и GetUserPublicProfilesHandler, поэтому
 * лежит в Application/Result, а не в папке одного из двух Query.
 *
 * avatar — публичное медиа модуля Media (оригинал + конверсии) или null, если у пользователя нет
 * аватара либо его медиа недоступно: сервер не выдумывает ссылку-заглушку, дефолтный аватар ставит
 * клиент. Потребитель одной ссылки (уведомления, пуш) берёт avatar?->original?->url.
 */
final readonly class UserProfileResult
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaDto|null $avatar,
        public Locale $locale,
    ) {}

    /**
     * Собирает профиль из доменной сущности и уже разрешённого набора аватаров: сам факт обращения к
     * Media (пакетным вызовом на весь набор пользователей) — ответственность вызывающего Query
     * handler-а, здесь только сборка формы ответа.
     */
    public static function fromUser(User $user, MediaDtoCollection $avatars): self
    {
        return new self(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatar: self::avatar(user: $user, avatars: $avatars),
            locale: $user->locale,
        );
    }

    private static function avatar(User $user, MediaDtoCollection $avatars): MediaDto|null
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return null;
        }

        $media = $avatars->get($mediaId);

        // Аватара нет, если медиа недоступно или оригинал удалён (readyOriginalRemoved): отдаём null
        // «всё или ничего», без конверсий удалённого оригинала. Заглушку рисует клиент.
        if ($media === null || $media->original === null) {
            return null;
        }

        return $media;
    }
}
