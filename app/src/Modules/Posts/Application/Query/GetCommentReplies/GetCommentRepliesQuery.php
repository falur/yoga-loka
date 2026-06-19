<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetCommentReplies;

final readonly class GetCommentRepliesQuery
{
    public function __construct(
        public string $commentId,
        public string $authUserId,
        public string|null $cursor,
        public int $limit,
    ) {}
}
