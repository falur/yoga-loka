<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfiles;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\User\Application\Result\UserProfileResultCollection;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичные профили нескольких пользователей (авторы ленты/комментариев, проверка упоминаний).
 * Несуществующие идентификаторы просто отсутствуют в результате — вызывающий сам сверяет
 * запрошенные и найденные, чтобы при необходимости вернуть 422 на несуществующее упоминание.
 *
 * Аватары всего набора разрешаются одним обращением к Media, поэтому число вызовов соседа не
 * зависит от числа пользователей в наборе.
 */
final readonly class GetUserPublicProfilesHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private MediaContract $media,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfilesQuery $query): UserProfileResultCollection
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            $query->userIds,
        );

        $users = $this->userRepository->findByIds(...$userIds);

        return UserProfileResultCollection::fromUsers(users: $users, avatars: $this->resolveAvatars($users));
    }

    private function resolveAvatars(UserCollection $users): MediaDtoCollection
    {
        $mediaIds = UserProfileResultCollection::avatarMediaIds($users);

        if ($mediaIds === []) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds($mediaIds);
    }
}
