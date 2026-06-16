<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RevokeUserSession;

final readonly class RevokeUserSessionCommand
{
    public function __construct(
        public string $userId,
        public string $sessionId,
    ) {}
}
