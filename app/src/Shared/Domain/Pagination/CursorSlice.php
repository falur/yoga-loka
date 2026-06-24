<?php

declare(strict_types=1);

namespace App\Shared\Domain\Pagination;

use Illuminate\Support\Collection;

/**
 * Срез одной страницы cursor-пагинации: видимые элементы и курсор следующей страницы.
 *
 * Источник — набор с запасом: репозиторий запрашивает на один элемент больше лимита
 * (`limit + 1`), чтобы по самому факту лишнего элемента понять, есть ли следующая страница,
 * без отдельного COUNT-запроса. `fromOverfetched()` отрезает первые `limit` элементов и
 * вычисляет курсор из последнего отданного. Курсор — value() UUID v7 id последней строки
 * (см. rules.md «Cursor-пагинация по UUID v7 `id`»). Тип конкретной коллекции сохраняется:
 * `$items` той же типизированной коллекции, что и `overfetched`.
 *
 * @template TValue
 * @template TCollection of Collection<int, TValue>
 */
final readonly class CursorSlice
{
    /**
     * @param TCollection $items
     */
    private function __construct(
        public Collection $items,
        public string|null $nextCursor,
    ) {}

    /**
     * @template TOverValue
     * @template TOverCollection of Collection<int, TOverValue>
     *
     * @param TOverCollection $overfetched набор до `limit + 1` элементов (как вернул репозиторий)
     * @param callable(TOverValue): string $cursorOf курсор элемента (обычно `id->value()`)
     *
     * @return self<TOverValue, TOverCollection>
     */
    public static function fromOverfetched(Collection $overfetched, int $limit, callable $cursorOf): self
    {
        $visible = $overfetched->take($limit);
        $hasMore = $overfetched->count() > $limit;
        $last = $visible->last();

        return new self(
            items: $visible,
            nextCursor: $hasMore && $last !== null ? $cursorOf($last) : null,
        );
    }
}
