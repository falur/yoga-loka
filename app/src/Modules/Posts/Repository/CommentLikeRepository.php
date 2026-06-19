<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<CommentLike>
 */
final class CommentLikeRepository extends AbstractRepository
{
    public function findByCommentAndUser(CommentId $commentId, UserId $userId): CommentLike|null
    {
        return $this->findOne(['comment_id' => $commentId->value(), 'user_id' => $userId->value()]);
    }

    public function existsByCommentAndUser(CommentId $commentId, UserId $userId): bool
    {
        return $this->findByCommentAndUser(commentId: $commentId, userId: $userId) !== null;
    }

    /**
     * Лайки пользователя по набору комментариев — для флага likedByMe в листингах без N+1.
     */
    public function findByUserAndCommentIds(UserId $userId, CommentId ...$commentIds): CommentLikeCollection
    {
        if ($commentIds === []) {
            return new CommentLikeCollection();
        }

        return new CommentLikeCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('comment_id', 'in', new Parameter(\array_map(
                    static fn(CommentId $commentId): string => $commentId->value(),
                    $commentIds,
                )))
                ->fetchAll(),
        );
    }
}
