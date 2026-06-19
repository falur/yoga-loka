<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

/**
 * @param list<string> $mediaIds
 * @param list<string> $tags
 * @param list<string> $mentions
 */
final readonly class CreatePostCommand
{
    /**
     * @param list<string> $mediaIds
     * @param list<string> $tags
     * @param list<string> $mentions
     */
    public function __construct(
        public string $authUserId,
        public string|null $text,
        public bool $draft,
        public array $mediaIds,
        public array $tags,
        public array $mentions,
    ) {}
}
