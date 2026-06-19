<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfiles;

use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичные профили нескольких пользователей (авторы ленты/комментариев, проверка упоминаний).
 * Несуществующие идентификаторы просто отсутствуют в результате — вызывающий сам сверяет
 * запрошенные и найденные, чтобы при необходимости вернуть 422 на несуществующее упоминание.
 */
final readonly class GetUserPublicProfilesHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPublicProfileAssembler $assembler,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfilesQuery $query): UserPublicProfileCollection
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            $query->userIds,
        );

        /** @var UserCollection $users */
        $users = $this->userRepository->findByIds(...$userIds);

        /** @var array<int, UserPublicProfileView> $profiles */
        $profiles = $users
            ->map(fn(User $user): UserPublicProfileView => $this->assembler->fromUser($user))
            ->all();

        return new UserPublicProfileCollection($profiles);
    }
}
