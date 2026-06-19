<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Cycle\WhenSelect;

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
        $cursorId = $cursor?->value();

        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }

    /**
     * Комментарии верхнего уровня записи (parent_comment_id IS NULL), без удалённых, cursor-пагинация.
     */
    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        $cursorId = $cursor?->value();

        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->where('parent_comment_id', '=', null)
                ->where('deleted_at', '=', null)
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }

    public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
    {
        $cursorId = $cursor?->value();

        return new CommentCollection(
            $this->select()
                ->where('parent_comment_id', $parentId->value())
                ->where('deleted_at', '=', null)
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }
}
