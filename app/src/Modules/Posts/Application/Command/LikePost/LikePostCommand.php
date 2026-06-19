<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\LikePost;

final readonly class LikePostCommand
{
    public function __construct(
        public string $authUserId,
        public string $postId,
    ) {}
}
