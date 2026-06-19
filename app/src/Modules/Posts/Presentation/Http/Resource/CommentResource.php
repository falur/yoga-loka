<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\CommentView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

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
        public string $createdAt,
        public AuthorResource $author,
    ) {}

    public static function fromView(CommentView $comment): self
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
            author: AuthorResource::fromView($comment->author),
        );
    }
}
