<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Posts\Application\Data\CommentViewerFlagsData;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, CommentResult>
 */
final class CommentResultCollection extends TypedCollection
{
    /**
     * Строит страницу результата из доменных комментариев и уже готовых пакетных карт (авторы,
     * флаг likedByMe): общая сборка для GetPostComments/GetCommentReplies, вынесенная сюда, чтобы
     * не дублировать один и тот же цикл в обоих Query handler-ах.
     *
     * @param array<string, AuthorResult> $authors
     */
    public static function fromComments(
        CommentCollection $comments,
        array $authors,
        CommentViewerFlagsData $likedCommentIds,
    ): self {
        return new self(
            $comments->toBase()->map(static fn(Comment $comment): CommentResult => CommentResult::fromEntity(
                comment: $comment,
                author: AuthorResult::require(authors: $authors, userId: $comment->userId->value()),
                likedByMe: $likedCommentIds->isLiked($comment->id->value()),
            )),
        );
    }
}
