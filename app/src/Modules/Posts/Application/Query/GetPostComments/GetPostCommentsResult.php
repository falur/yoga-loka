<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

use App\Modules\Posts\Application\Result\CommentResultCollection;

final readonly class GetPostCommentsResult
{
    public function __construct(
        public CommentResultCollection $comments,
        public string|null $nextCursor,
    ) {}
}
