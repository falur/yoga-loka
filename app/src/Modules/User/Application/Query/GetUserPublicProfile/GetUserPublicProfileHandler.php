<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfile;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\User\Application\Result\UserProfileResult;
use App\Modules\User\Application\Result\UserProfileResultCollection;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Exception\UserNotFoundException;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичный профиль одного пользователя по идентификатору. Несуществующий пользователь — 404.
 *
 * Аватар разрешается через публичный контракт Media тем же пакетным путём, что и у набора из
 * нескольких пользователей (GetUserPublicProfilesHandler) — набор из одного элемента, не отдельный
 * одиночный вызов.
 */
final readonly class GetUserPublicProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private MediaContract $media,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfileQuery $query): UserProfileResult
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId))
            ?? throw new UserNotFoundException();

        return UserProfileResult::fromUser(user: $user, avatars: $this->resolveAvatars(new UserCollection([$user])));
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
