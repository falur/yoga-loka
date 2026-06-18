<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\TagId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<PostTag>
 */
final class PostTagRepository extends AbstractRepository
{
    public function findByPostId(PostId $postId): PostTagCollection
    {
        return new PostTagCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->fetchAll(),
        );
    }

    public function findByTagId(TagId $tagId): PostTagCollection
    {
        return new PostTagCollection(
            $this->select()
                ->where('tag_id', $tagId->value())
                ->fetchAll(),
        );
    }
}
