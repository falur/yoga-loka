<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<PostMedia>
 */
final class PostMediaRepository extends AbstractRepository
{
    public function findByPostId(PostId $postId): PostMediaCollection
    {
        return new PostMediaCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }
}
