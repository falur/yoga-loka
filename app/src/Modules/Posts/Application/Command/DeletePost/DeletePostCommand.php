<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DeletePost;

final readonly class DeletePostCommand
{
    public function __construct(
        public string $authUserId,
        public string $postId,
    ) {}
}
