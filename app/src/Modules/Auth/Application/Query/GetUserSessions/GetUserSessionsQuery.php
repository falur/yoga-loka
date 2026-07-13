<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Query\GetUserSessions;

final readonly class GetUserSessionsQuery
{
    public function __construct(
        public string $userId,
        public string $currentSessionId,
    ) {}
}
