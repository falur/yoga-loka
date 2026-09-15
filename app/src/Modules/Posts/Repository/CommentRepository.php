<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<Comment>
 */
final class CommentRepository extends AbstractRepository
{
    public function findById(CommentId $commentId): Comment|null
    {
        return $this->findByPK($commentId->value());
    }

    public function findByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    /**
     * Комментарии верхнего уровня записи (parent_comment_id IS NULL), без удалённых, cursor-пагинация.
     */
    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->where('parent_comment_id', '=', null)
                ->where('deleted_at', '=', null)
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('parent_comment_id', $parentId->value())
                ->where('deleted_at', '=', null)
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }
}
