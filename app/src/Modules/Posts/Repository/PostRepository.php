<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;

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
     * Набор записей по идентификаторам одним запросом — для пакетной сборки оригиналов репостов в
     * листинге без N+1. Soft-deleted не прячется неявно: видимость решает вызывающий через политику.
     */
    public function findByIds(PostId ...$postIds): PostCollection
    {
        if ($postIds === []) {
            return new PostCollection();
        }

        return new PostCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->fetchAll(),
        );
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

        return new PostCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->when(
                    condition: $statusValue !== null,
                    callback: static function (WhenSelect $query) use ($statusValue): void {
                        $query->where('status', $statusValue);
                    },
                )
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    /**
     * Видимая лента автора: как findByUserId, но скрывает мягко удалённые записи (deleted_at IS NULL).
     * Статус опционален: вызывающий передаёт Published для чужой ленты и null (все статусы) для своей.
     * excludeStatus исключает один статус из выборки — для своей ленты это Blocked, чтобы
     * заблокированная модерацией запись не была видна никому (как в PostVisibilityPolicy и GetPost).
     */
    public function findVisibleByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostStatus|null $excludeStatus,
        PostId|null $cursor,
        int $limit,
    ): PostCollection {
        $statusValue = $status?->value;
        $excludeStatusValue = $excludeStatus?->value;

        return new PostCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('deleted_at', '=', null)
                ->when(
                    condition: $statusValue !== null,
                    callback: static function (WhenSelect $query) use ($statusValue): void {
                        $query->where('status', $statusValue);
                    },
                )
                ->when(
                    condition: $excludeStatusValue !== null,
                    callback: static function (WhenSelect $query) use ($excludeStatusValue): void {
                        $query->where('status', '!=', $excludeStatusValue);
                    },
                )
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
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
