<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

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
}
