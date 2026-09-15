<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostLikeCollection;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<PostLike>
 */
final class PostLikeRepository extends AbstractRepository
{
    public function findByPostAndUser(PostId $postId, UserId $userId): PostLike|null
    {
        return $this->findOne(['post_id' => $postId->value(), 'user_id' => $userId->value()]);
    }

    public function existsByPostAndUser(PostId $postId, UserId $userId): bool
    {
        return $this->findByPostAndUser(postId: $postId, userId: $userId) !== null;
    }

    public function findByUserId(UserId $userId): PostLikeCollection
    {
        return new PostLikeCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    /**
     * Лайки пользователя по набору записей — для флага likedByMe в листингах без N+1.
     */
    public function findByUserAndPostIds(UserId $userId, PostId ...$postIds): PostLikeCollection
    {
        if ($postIds === []) {
            return new PostLikeCollection();
        }

        return new PostLikeCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->fetchAll(),
        );
    }
}
