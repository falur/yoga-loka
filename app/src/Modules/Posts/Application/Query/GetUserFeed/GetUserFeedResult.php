<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

use App\Modules\Posts\Application\Result\PostResultCollection;

final readonly class GetUserFeedResult
{
    public function __construct(
        public PostResultCollection $posts,
        public string|null $nextCursor,
    ) {}
}
