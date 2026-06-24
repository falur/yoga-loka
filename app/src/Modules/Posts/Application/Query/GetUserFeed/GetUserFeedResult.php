<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

use App\Modules\Posts\Application\View\PostViewCollection;

final readonly class GetUserFeedResult
{
    public function __construct(
        public PostViewCollection $posts,
        public string|null $nextCursor,
    ) {}
}
