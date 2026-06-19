<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Entity\User;
use App\Shared\Infrastructure\Configuration\User\UserConfig;

/**
 * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
 * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
 * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
 *
 * FindMediaUrl вызывается напрямую (а не через QueryBus), потому что это единственный сценарий,
 * возвращающий nullable, и обёртка шины теряет null из вывода типов — прямой вызов сохраняет
 * контракт «медиа недоступно -> null -> дефолт» без try-catch и без подавления статанализа.
 */
final readonly class UserPublicProfileAssembler
{
    // Аватары публичны (прямой URL без срока), поэтому TTL фактически не используется; значение
    // нужно только для приватной ветки FindMediaUrl и берётся техническим дефолтом рядом с местом.
    private const int AVATAR_URL_TTL_SECONDS = 3600;

    public function __construct(
        private FindMediaUrlHandler $findMediaUrlHandler,
        private UserConfig $userConfig,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatarUrl: $this->resolveAvatarUrl($user),
            locale: $user->locale->value,
        );
    }

    private function resolveAvatarUrl(User $user): string
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        $mediaUrl = $this->findMediaUrlHandler->handle(
            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
        );

        if ($mediaUrl === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        return $mediaUrl->url;
    }
}
