<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle;

use Cycle\ORM\Select;

/**
 * Select с условным построением запроса: метод when() выполняет замыкание только при
 * истинном условии, поэтому условные where пишутся внутри fluent-цепочки без разрыва на if.
 *
 * @template-covariant TEntity of object
 *
 * @extends Select<TEntity>
 */
class WhenSelect extends Select
{
    /**
     * Выполнить замыкание над запросом, если условие истинно.
     *
     * @param callable(self<TEntity>): void $callback
     *
     * @return $this
     */
    public function when(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md «Cursor-пагинация по UUID v7 `id`»): сортировка
     * id DESC, при заданном курсоре берём строки строго старше него (id меньше курсора), затем
     * ограничиваем страницу. Курсор — value() id последней строки предыдущей страницы; null —
     * первая страница. Применяется последним звеном цепочки, после where-фильтров запроса.
     *
     * @return static
     */
    public function cursorById(string|null $cursor, int $limit): static
    {
        return $this
            ->when(
                condition: $cursor !== null,
                callback: static function (self $query) use ($cursor): void {
                    $query->where('id', '<', $cursor);
                },
            )
            ->orderBy(expression: 'id', direction: 'DESC')
            ->limit($limit);
    }
}
