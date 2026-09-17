<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetMyFeed;

final readonly class GetMyFeedQuery
{
    public function __construct(
        public string $authUserId,
        public string|null $cursor,
        public int $limit,
    ) {}
}
