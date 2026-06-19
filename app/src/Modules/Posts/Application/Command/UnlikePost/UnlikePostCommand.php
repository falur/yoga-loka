<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\UnlikePost;

final readonly class UnlikePostCommand
{
    public function __construct(
        public string $authUserId,
        public string $postId,
    ) {}
}
