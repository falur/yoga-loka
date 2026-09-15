<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;

/**
 * Собирает публичные профили из доменных сущностей User, разрешая аватары через публичный контракт
 * Media. Сборка пакетная: идентификаторы аватаров всего набора уходят к соседу одним вызовом, поэтому
 * число обращений к Media не зависит от числа пользователей в наборе. Профиль одного пользователя —
 * тот же путь для набора из одного элемента.
 *
 * Аватара может не быть: не задан у пользователя, медиа недоступно (удалено/не готово) или у медиа
 * удалён оригинал — avatar = null. Сервер не выдумывает ссылку-заглушку, дефолтный аватар подставляет
 * клиент. Контракт Media не возвращает недоступные медиа, поэтому правило выражается отсутствием
 * идентификатора в наборе и не требует try-catch.
 */
final readonly class UserPublicProfileAssembler
{
    public function __construct(
        private MediaContract $media,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return $this->profile(user: $user, avatars: $this->resolveAvatars(new UserCollection([$user])));
    }

    public function fromUsers(UserCollection $users): UserPublicProfileCollection
    {
        $avatars = $this->resolveAvatars($users);

        return new UserPublicProfileCollection(
            $users->toBase()->map(
                fn(User $user): UserPublicProfileView => $this->profile(user: $user, avatars: $avatars),
            ),
        );
    }

    private function resolveAvatars(UserCollection $users): MediaDtoCollection
    {
        $mediaIds = $this->avatarMediaIds($users);

        if ($mediaIds === []) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds($mediaIds);
    }

    /**
     * @return list<string>
     */
    private function avatarMediaIds(UserCollection $users): array
    {
        return \array_values(\array_unique(
            $users->toBase()
                ->filter(static fn(User $user): bool => !$user->avatar->isEmpty())
                ->map(static fn(User $user): string => (string) $user->avatar)
                ->all(),
        ));
    }

    private function profile(User $user, MediaDtoCollection $avatars): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatar: $this->avatar(user: $user, avatars: $avatars),
            locale: $user->locale,
        );
    }

    private function avatar(User $user, MediaDtoCollection $avatars): MediaDto|null
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
