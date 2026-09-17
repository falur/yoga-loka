<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetMyFeed;

use App\Modules\Posts\Application\Result\PostResultCollection;

final readonly class GetMyFeedResult
{
    public function __construct(
        public PostResultCollection $posts,
        public string|null $nextCursor,
    ) {}
}
