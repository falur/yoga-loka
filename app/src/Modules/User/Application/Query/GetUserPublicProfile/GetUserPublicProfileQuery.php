<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfile;

final readonly class GetUserPublicProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
