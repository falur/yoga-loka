<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Posts\Domain\Entity\Comment;

/**
 * Комментарий в ответе: текст, ссылка на родителя (для ответа), автор, счётчики и флаг
 * «оценил я».
 */
final readonly class CommentResult
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
        public AuthorResult $author,
    ) {}

    public static function fromEntity(Comment $comment, AuthorResult $author, bool $likedByMe): self
    {
        return new self(
            id: $comment->id->value(),
            postId: $comment->postId->value(),
            parentId: $comment->parent->value(),
            text: $comment->text->value(),
            likesCount: $comment->likesCount->value(),
            repliesCount: $comment->repliesCount->value(),
            likedByMe: $likedByMe,
            createdAt: $comment->createdAt,
            author: $author,
        );
    }
}
