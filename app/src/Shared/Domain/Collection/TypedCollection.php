<?php

declare(strict_types=1);

namespace App\Shared\Domain\Collection;

use Illuminate\Support\Collection;

/**
 * Общий базовый класс для всех типизированных коллекций проекта. Наследует
 * Illuminate Collection и добавляет правильно типизированное преобразование
 * коллекции в список, чтобы на местах вызова не писать
 * \array_values($coll->toBase()->map($fn)->all()).
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Collection<TKey, TValue>
 */
abstract class TypedCollection extends Collection
{
    /**
     * Преобразовать элементы в другой тип и вернуть список с последовательными
     * ключами. Идёт через toBase(), чтобы смена типа элемента не ломала
     * обобщённый тип final-коллекции; array_values даёт PHPStan-тип list<TNew>.
     *
     * @template TNew
     *
     * @param callable(TValue): TNew $callback
     *
     * @return list<TNew>
     */
    public function mapToList(callable $callback): array
    {
        return \array_values($this->toBase()->map($callback)->all());
    }
}
