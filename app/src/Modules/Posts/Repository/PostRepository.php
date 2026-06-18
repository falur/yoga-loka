<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Cycle\WhenSelect;

/**
 * @extends AbstractRepository<Post>
 */
final class PostRepository extends AbstractRepository
{
    public function findById(PostId $postId): Post|null
    {
        return $this->findByPK($postId->value());
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md): id DESC, при наличии курсора берём строки строго
     * старше курсора. Фильтр по статусу опционален. Soft-deleted не прячется неявно — это решает вызывающий.
     */
    public function findByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostId|null $cursor,
        int $limit,
    ): PostCollection {
        $statusValue = $status?->value;
        $cursorId = $cursor?->value();

        return new PostCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->when(
                    condition: $statusValue !== null,
                    callback: static function (WhenSelect $query) use ($statusValue): void {
                        $query->where('status', $statusValue);
                    },
                )
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }

    public function findRepostsOf(PostId $postId): PostCollection
    {
        return new PostCollection(
            $this->select()
                ->where('parent_post_id', $postId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }
}
