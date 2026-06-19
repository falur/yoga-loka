<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

use App\Modules\Posts\Application\View\CommentView;

/**
 * @param list<CommentView> $comments
 */
final readonly class GetPostCommentsResult
{
    /**
     * @param list<CommentView> $comments
     */
    public function __construct(
        public array $comments,
        public string|null $nextCursor,
    ) {}
}
