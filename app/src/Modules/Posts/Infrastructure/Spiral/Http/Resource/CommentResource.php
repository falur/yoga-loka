<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Posts\Application\Result\CommentResult;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

final readonly class CommentResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $postId,
        public string|null $parentId,
        public string $text,
        public int $likesCount,
        public int $repliesCount,
        public bool $likedByMe,
        public \DateTimeImmutable $createdAt,
        public AuthorResource $author,
    ) {}

    public static function fromResult(CommentResult $comment): self
    {
        return new self(
            id: $comment->id,
            postId: $comment->postId,
            parentId: $comment->parentId,
            text: $comment->text,
            likesCount: $comment->likesCount,
            repliesCount: $comment->repliesCount,
            likedByMe: $comment->likedByMe,
            createdAt: $comment->createdAt,
            author: AuthorResource::fromResult($comment->author),
        );
    }
}
