<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\CheckUsersExist;

final readonly class CheckUsersExistQuery
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}
}
