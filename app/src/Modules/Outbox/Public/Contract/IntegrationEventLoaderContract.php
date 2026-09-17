<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Contract;

interface IntegrationEventLoaderContract
{
    /**
     * Восстанавливает событие по идентификатору записи outbox и сверяет его с ожидаемым классом:
     * чужое событие через этот вызов не пройдёт.
     *
     * @template TIntegrationEvent of IntegrationEvent
     * @param class-string<TIntegrationEvent> $expectedEventClass
     * @return TIntegrationEvent
     */
    public function load(string $outboxEventId, string $expectedEventClass): IntegrationEvent;
}
