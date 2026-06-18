<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostLikeCollection;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

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
}
