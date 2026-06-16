<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Logout;

final readonly class LogoutCommand
{
    public function __construct(
        public string $authSessionId,
    ) {}
}
