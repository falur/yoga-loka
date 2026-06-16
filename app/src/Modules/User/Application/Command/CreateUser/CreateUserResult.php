<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

final readonly class CreateUserResult
{
    public function __construct(
        public string $userId,
    ) {}
}
