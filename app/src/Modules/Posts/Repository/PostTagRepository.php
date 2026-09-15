<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

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

    /**
     * Теги набора записей — для сборки листинга ленты без N+1.
     */
    public function findByPostIds(PostId ...$postIds): PostTagCollection
    {
        if ($postIds === []) {
            return new PostTagCollection();
        }

        return new PostTagCollection(
            $this->select()
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
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
