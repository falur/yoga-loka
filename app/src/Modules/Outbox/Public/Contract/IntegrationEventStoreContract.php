<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Contract;

interface IntegrationEventStoreContract
{
    /**
     * Сохраняет событие в outbox в транзакции вызывающего и возвращает идентификатор записи.
     * Своей транзакции контракт не открывает и flush не делает: это остаётся за источником.
     */
    public function add(IntegrationEvent $integrationEvent): string;
}
