<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

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
}
