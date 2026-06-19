<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CommentPost;

/**
 * @param list<string> $mentions
 */
final readonly class CommentPostCommand
{
    /**
     * @param list<string> $mentions
     */
    public function __construct(
        public string $authUserId,
        public string $postId,
        public string $text,
        public array $mentions,
    ) {}
}
