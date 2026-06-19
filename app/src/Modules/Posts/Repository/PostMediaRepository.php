<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

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

    /**
     * Медиа набора записей — для сборки листинга ленты без N+1. Сортировка по post_id и позиции,
     * чтобы вызывающий мог сгруппировать вложения по записи.
     */
    public function findByPostIds(PostId ...$postIds): PostMediaCollection
    {
        if ($postIds === []) {
            return new PostMediaCollection();
        }

        return new PostMediaCollection(
            $this->select()
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->orderBy(expression: 'post_id', direction: 'ASC')
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }
}
