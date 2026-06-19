<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

final readonly class GetUserFeedQuery
{
    public function __construct(
        public string $ownerUserId,
        public string $authUserId,
        public string|null $cursor,
        public int $limit,
    ) {}
}
