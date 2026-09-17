<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Dto;

use App\Modules\Notifications\Public\Enum\NotificationChannel;
use App\Shared\Domain\Collection\TypedCollection;
use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Набор каналов доставки: определение вида уведомления объявляет им каналы, включённые по
 * умолчанию, пока у пользователя нет персональной настройки по каналу. Порядок элементов частью
 * контракта не является — значим только состав.
 *
 * @extends TypedCollection<int, NotificationChannel>
 */
final class NotificationChannelCollection extends TypedCollection
{
    public static function of(NotificationChannel ...$channels): self
    {
        $collection = new self(\array_values($channels));

        // Fail-fast на ошибку декларации: канал, названный в определении вида дважды, не схлопывается
        // молча в один — определение чинится, а не подстраивается набором.
        if ($collection->unique(strict: true)->count() !== $collection->count()) {
            throw new InvalidDomainValueException('Канал по умолчанию указан повторно.');
        }

        return $collection;
    }

    public function includes(NotificationChannel $channel): bool
    {
        return $this->contains(static fn(NotificationChannel $candidate): bool => $candidate === $channel);
    }
}
