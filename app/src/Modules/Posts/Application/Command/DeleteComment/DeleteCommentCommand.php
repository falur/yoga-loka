<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DeleteComment;

final readonly class DeleteCommentCommand
{
    public function __construct(
        public string $authUserId,
        public string $commentId,
    ) {}
}
