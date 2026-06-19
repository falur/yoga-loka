<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\UnlikeComment;

final readonly class UnlikeCommentCommand
{
    public function __construct(
        public string $authUserId,
        public string $commentId,
    ) {}
}
