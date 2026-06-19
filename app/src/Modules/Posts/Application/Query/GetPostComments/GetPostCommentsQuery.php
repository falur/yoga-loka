<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

final readonly class GetPostCommentsQuery
{
    public function __construct(
        public string $postId,
        public string $authUserId,
        public string|null $cursor,
        public int $limit,
    ) {}
}
