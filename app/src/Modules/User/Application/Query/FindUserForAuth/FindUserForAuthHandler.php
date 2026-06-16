<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\FindUserForAuth;

use App\Modules\User\Application\Dto\UserAuthView;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class FindUserForAuthHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(FindUserForAuthQuery $query): UserAuthView|null
    {
        $user = $this->userRepository->findByEmail(Email::fromString($query->email));

        if ($user === null) {
            return null;
        }

        return new UserAuthView(
            userId: $user->id->value(),
            canSignIn: $user->isActive(),
        );
    }
}
