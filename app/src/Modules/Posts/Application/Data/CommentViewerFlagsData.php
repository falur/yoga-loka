<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

/**
 * Признак «оценил я» по набору комментариев для одного зрителя: набор идентификаторов
 * комментариев, у которых есть лайк этого зрителя. Ряд выборки (лайк) превращает в это значение
 * фабрика самого класса — Reader ключи ряда не читает.
 */
final readonly class CommentViewerFlagsData
{
    /**
     * @param array<string, true> $likedCommentIds
     */
    private function __construct(
        private array $likedCommentIds,
    ) {}

    /**
     * @param iterable<array-key, array<non-empty-string, scalar|null>> $rows
     */
    public static function fromDatabaseRows(iterable $rows): self
    {
        $likedCommentIds = [];

        foreach ($rows as $row) {
            $likedCommentIds[(string) $row['commentId']] = true;
        }

        return new self($likedCommentIds);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isLiked(string $commentId): bool
    {
        return isset($this->likedCommentIds[$commentId]);
    }
}
