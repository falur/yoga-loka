<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPost;

final readonly class GetPostQuery
{
    public function __construct(
        public string $postId,
        public string $authUserId,
    ) {}
}
