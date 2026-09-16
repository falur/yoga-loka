<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<PostBlock>
 */
final class CyclePostBlockRepository extends AbstractRepository implements PostBlockRepository
{
    #[\Override]
    public function findById(PostBlockId $postBlockId): PostBlock|null
    {
        return $this->findByPK($postBlockId->value());
    }

    #[\Override]
    public function findActiveByPostId(PostId $postId): PostBlock|null
    {
        return $this->select()
            ->where('post_id', $postId->value())
            ->where('unblocked_at', '=', null)
            ->fetchOne();
    }
}
