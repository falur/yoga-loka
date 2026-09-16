<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfile;

use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Domain\Exception\UserNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичный профиль одного пользователя по идентификатору. Несуществующий пользователь — 404.
 */
final readonly class GetUserPublicProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPublicProfileAssembler $assembler,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfileQuery $query): UserPublicProfileView
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId))
            ?? throw new UserNotFoundException();

        return $this->assembler->fromUser($user);
    }
}
