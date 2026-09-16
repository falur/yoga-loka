<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Read;

use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Application\Data\PostDataCollection;
use App\Modules\Posts\Application\Data\PostPageData;
use App\Modules\Posts\Application\Data\PostRelatedIds;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMediaColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostTagColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostTagEntity;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORMInterface;

/**
 * Чтение страницы ленты автора: сами записи и идентификаторы их вложений/меток. Только свои
 * таблицы, соседей не спрашивает, доменные сущности не создаёт. Своя и чужая лента — разные методы
 * с разным набором видимых статусов, а не один метод с ветвлением по роли зрителя.
 */
final readonly class CyclePostReader implements PostReader
{
    public function __construct(
        private ORMInterface $orm,
    ) {}

    #[\Override]
    public function myFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData
    {
        return $this->feed(
            ownerUserId: $ownerUserId,
            cursor: $cursor,
            limit: $limit,
            configure: static function (WhenSelect $query): void {
                $query->where(PostColumns::STATUS, '!=', PostStatus::Blocked->value);
            },
        );
    }

    #[\Override]
    public function userFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData
    {
        return $this->feed(
            ownerUserId: $ownerUserId,
            cursor: $cursor,
            limit: $limit,
            configure: static function (WhenSelect $query): void {
                $query->where(PostColumns::STATUS, PostStatus::Published->value);
            },
        );
    }

    /**
     * @param callable(WhenSelect<CyclePostEntity>): void $configure набор видимых статусов страницы
     */
    private function feed(string $ownerUserId, string|null $cursor, int $limit, callable $configure): PostPageData
    {
        // Запас в один ряд: по лишнему ряду CursorSlice видит, есть ли следующая страница, и
        // отдельный COUNT не нужен. Мягко удалённые записи скрыты всегда — их не видит никто.
        $query = $this->postSelect()
            ->where(PostColumns::USER_ID, $ownerUserId)
            ->where(PostColumns::DELETED_AT, '=', null);
        $configure($query);

        /** @var iterable<array-key, array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>> $rows */
        $rows = $query
            ->cursorById(cursor: $cursor, limit: $limit + 1)
            ->fetchData();

        $overfetched = PostDataCollection::fromDatabaseRows(
            rows: $rows,
            mediaIdsByPost: $this->mediaIdsByPost($rows),
            tagIdsByPost: $this->tagIdsByPost($rows),
        );

        $slice = CursorSlice::fromOverfetched(
            overfetched: $overfetched,
            limit: $limit,
            cursorOf: static fn(PostData $postData): string => $postData->id,
        );

        return new PostPageData(
            posts: $slice->items,
            nextCursor: $slice->nextCursor,
        );
    }

    /**
     * Вложения всех записей страницы одним запросом, упорядоченные по позиции: позиции вложений
     * записи всегда плотная последовательность 0..N-1 (назначаются подряд при создании и не
     * меняются), поэтому индекс элемента в списке равен его хранимой позиции — handler строит по
     * нему PostMediaResult без отдельного чтения позиции.
     *
     * Ключ ряда `fetchData()` — имя поля Cycle Entity (`postId`), а имя колонки из каталога
     * (`post_id`) идёт в условие запроса: это разные имена и подменять их нельзя.
     *
     * @param iterable<array-key, array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>> $rows
     *
     * @return array<string, PostRelatedIds>
     */
    private function mediaIdsByPost(iterable $rows): array
    {
        $postIds = $this->rowIds($rows);

        if ($postIds === []) {
            return [];
        }

        $mediaIdsByPost = [];

        /** @var iterable<array-key, array<non-empty-string, scalar|null>> $mediaRows */
        $mediaRows = $this->postMediaSelect()
            ->where(PostMediaColumns::POST_ID, 'in', new Parameter($postIds))
            ->orderBy(expression: PostMediaColumns::POST_ID, direction: 'ASC')
            ->orderBy(expression: PostMediaColumns::POSITION, direction: 'ASC')
            ->fetchData();

        foreach ($mediaRows as $mediaRow) {
            $mediaIdsByPost[(string) $mediaRow['postId']][] = (string) $mediaRow['mediaId'];
        }

        return \array_map(
            static fn(array $mediaIds): PostRelatedIds => new PostRelatedIds(ids: $mediaIds),
            $mediaIdsByPost,
        );
    }

    /**
     * Метки всех записей страницы одним запросом. Порядок меток в ответе частью контракта не
     * является (в post_tags нет колонки позиции), поэтому явной сортировки нет.
     *
     * @param iterable<array-key, array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>> $rows
     *
     * @return array<string, PostRelatedIds>
     */
    private function tagIdsByPost(iterable $rows): array
    {
        $postIds = $this->rowIds($rows);

        if ($postIds === []) {
            return [];
        }

        $tagIdsByPost = [];

        /** @var iterable<array-key, array<non-empty-string, scalar|null>> $tagRows */
        $tagRows = $this->postTagSelect()
            ->where(PostTagColumns::POST_ID, 'in', new Parameter($postIds))
            ->fetchData();

        foreach ($tagRows as $tagRow) {
            $tagIdsByPost[(string) $tagRow['postId']][] = (string) $tagRow['tagId'];
        }

        return \array_map(
            static fn(array $tagIds): PostRelatedIds => new PostRelatedIds(ids: $tagIds),
            $tagIdsByPost,
        );
    }

    /**
     * @param iterable<array-key, array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>> $rows
     *
     * @return list<string>
     */
    private function rowIds(iterable $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = PostData::idFromDatabaseRow($row);
        }

        return $ids;
    }

    /** @return WhenSelect<CyclePostEntity> */
    private function postSelect(): WhenSelect
    {
        /** @var WhenSelect<CyclePostEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CyclePostEntity::class);

        return $select;
    }

    /** @return WhenSelect<CyclePostMediaEntity> */
    private function postMediaSelect(): WhenSelect
    {
        /** @var WhenSelect<CyclePostMediaEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CyclePostMediaEntity::class);

        return $select;
    }

    /** @return WhenSelect<CyclePostTagEntity> */
    private function postTagSelect(): WhenSelect
    {
        /** @var WhenSelect<CyclePostTagEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CyclePostTagEntity::class);

        return $select;
    }
}
