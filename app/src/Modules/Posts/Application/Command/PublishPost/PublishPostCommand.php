<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

final readonly class PublishPostCommand
{
    public function __construct(
        public string $authUserId,
        public string $postId,
    ) {}
}
