<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public string $nickname,
        public string $locale,
    ) {}
}
