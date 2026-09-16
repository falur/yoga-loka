<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор данных чтения одной выборки записей. Ряды в объекты превращает фабрика самого PostData,
 * поэтому ключи ряда читаются в одном месте.
 *
 * @extends TypedCollection<int, PostData>
 */
final class PostDataCollection extends TypedCollection
{
    /**
     * @param iterable<array-key, array<non-empty-string, scalar|\App\Modules\Posts\Domain\Enum\PostStatus|\App\Modules\Posts\Domain\Enum\AttachmentType|\DateTimeImmutable|null>> $rows
     * @param array<string, PostRelatedIds> $mediaIdsByPost
     * @param array<string, PostRelatedIds> $tagIdsByPost
     */
    public static function fromDatabaseRows(iterable $rows, array $mediaIdsByPost, array $tagIdsByPost): self
    {
        $postDataCollection = new self();

        foreach ($rows as $row) {
            $id = PostData::idFromDatabaseRow($row);

            $postDataCollection->push(PostData::fromDatabaseRow(
                row: $row,
                mediaIds: ($mediaIdsByPost[$id] ?? new PostRelatedIds(ids: []))->ids,
                tagIds: ($tagIdsByPost[$id] ?? new PostRelatedIds(ids: []))->ids,
            ));
        }

        return $postDataCollection;
    }

    /**
     * @return list<string>
     */
    public function authorIds(): array
    {
        return \array_values(\array_unique($this
            ->toBase()
            ->map(static fn(PostData $postData): string => $postData->authorId)
            ->all()));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->mapToList(static fn(PostData $postData): string => $postData->id);
    }
}
