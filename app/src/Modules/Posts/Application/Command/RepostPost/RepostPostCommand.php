<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\RepostPost;

/**
 * @param list<string> $mediaIds
 * @param list<string> $tags
 * @param list<string> $mentions
 */
final readonly class RepostPostCommand
{
    /**
     * @param list<string> $mediaIds
     * @param list<string> $tags
     * @param list<string> $mentions
     */
    public function __construct(
        public string $authUserId,
        public string $postId,
        public string|null $text,
        public array $mediaIds,
        public array $tags,
        public array $mentions,
    ) {}
}
