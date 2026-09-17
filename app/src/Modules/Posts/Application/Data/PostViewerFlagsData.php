<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

/**
 * Признак «оценил я» по набору записей для одного зрителя: набор идентификаторов записей, у
 * которых есть лайк этого зрителя. Ряд выборки (лайк) превращает в это значение фабрика самого
 * класса — Reader ключи ряда не читает.
 */
final readonly class PostViewerFlagsData
{
    /**
     * @param array<string, true> $likedPostIds
     */
    private function __construct(
        private array $likedPostIds,
    ) {}

    /**
     * @param iterable<array-key, array<non-empty-string, scalar|null>> $rows
     */
    public static function fromDatabaseRows(iterable $rows): self
    {
        $likedPostIds = [];

        foreach ($rows as $row) {
            $likedPostIds[(string) $row['postId']] = true;
        }

        return new self($likedPostIds);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isLiked(string $postId): bool
    {
        return isset($this->likedPostIds[$postId]);
    }
}
