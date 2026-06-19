<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\ReplyComment;

/**
 * @param list<string> $mentions
 */
final readonly class ReplyCommentCommand
{
    /**
     * @param list<string> $mentions
     */
    public function __construct(
        public string $authUserId,
        public string $commentId,
        public string $text,
        public array $mentions,
    ) {}
}
