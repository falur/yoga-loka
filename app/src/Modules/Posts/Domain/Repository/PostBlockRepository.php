<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Repository;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;

/**
 * Хранение блокировок модерации. Корень агрегата — PostBlock: блокировка имеет свой жизненный
 * цикл и переживает запись, поэтому она не схлопнута с PostRepository.
 */
interface PostBlockRepository
{
    public function findById(PostBlockId $postBlockId): PostBlock|null;

    public function findActiveByPostId(PostId $postId): PostBlock|null;
}
