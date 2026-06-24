<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

use App\Modules\Posts\Application\View\CommentViewCollection;

final readonly class GetPostCommentsResult
{
    public function __construct(
        public CommentViewCollection $comments,
        public string|null $nextCursor,
    ) {}
}
