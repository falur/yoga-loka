<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetCommentReplies;

use App\Modules\Posts\Application\Result\CommentResultCollection;

final readonly class GetCommentRepliesResult
{
    public function __construct(
        public CommentResultCollection $replies,
        public string|null $nextCursor,
    ) {}
}
