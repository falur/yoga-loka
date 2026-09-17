<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetComment;

final readonly class GetCommentQuery
{
    public function __construct(
        public string $commentId,
        public string $authUserId,
    ) {}
}
