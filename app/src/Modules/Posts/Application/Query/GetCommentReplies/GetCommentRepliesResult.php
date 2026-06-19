<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetCommentReplies;

use App\Modules\Posts\Application\View\CommentView;

/**
 * @param list<CommentView> $replies
 */
final readonly class GetCommentRepliesResult
{
    /**
     * @param list<CommentView> $replies
     */
    public function __construct(
        public array $replies,
        public string|null $nextCursor,
    ) {}
}
