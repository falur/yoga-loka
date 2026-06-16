<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\FindUserForAuth;

final readonly class FindUserForAuthQuery
{
    public function __construct(
        public string $email,
    ) {}
}
