<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfiles;

/**
 * @param list<string> $userIds
 */
final readonly class GetUserPublicProfilesQuery
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}
}
