<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

use App\Modules\Posts\Application\View\PostView;

/**
 * @param list<PostView> $posts
 */
final readonly class GetUserFeedResult
{
    /**
     * @param list<PostView> $posts
     */
    public function __construct(
        public array $posts,
        public string|null $nextCursor,
    ) {}
}
