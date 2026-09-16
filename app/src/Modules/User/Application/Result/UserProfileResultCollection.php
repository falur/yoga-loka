<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Result;

use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, UserProfileResult>
 */
final class UserProfileResultCollection extends TypedCollection
{
    /**
     * Собирает набор профилей из доменных сущностей и уже разрешённого набора аватаров: разрешение
     * аватаров одним пакетным вызовом к Media — ответственность вызывающего Query handler-а.
     */
    public static function fromUsers(UserCollection $users, MediaDtoCollection $avatars): self
    {
        return new self(
            $users->toBase()->map(
                fn(User $user): UserProfileResult => UserProfileResult::fromUser(user: $user, avatars: $avatars),
            ),
        );
    }

    /**
     * Идентификаторы аватаров всего набора пользователей без повторов — вход для одного пакетного
     * вызова MediaContract::urlsByIds(), общий для сборки как одного профиля, так и набора.
     *
     * @return list<string>
     */
    public static function avatarMediaIds(UserCollection $users): array
    {
        return \array_values(\array_unique(
            $users->toBase()
                ->filter(static fn(User $user): bool => !$user->avatar->isEmpty())
                ->map(static fn(User $user): string => (string) $user->avatar)
                ->all(),
        ));
    }
}
