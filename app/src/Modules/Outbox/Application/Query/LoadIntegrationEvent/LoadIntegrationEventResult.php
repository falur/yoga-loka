<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Query\LoadIntegrationEvent;

use App\Modules\Outbox\Application\Exception\OutboxMessageLoadingException;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final readonly class LoadIntegrationEventResult
{
    public function __construct(
        public IntegrationEvent $integrationEvent,
    ) {}

    /**
     * Типизированное чтение восстановленного события: сверка с ожидаемым классом живёт здесь,
     * рядом со сценарием, потому что именно она даёт вызывающему точный тип. Сериализатор мог
     * вернуть другой класс, чем записан в строке outbox, — такое событие наружу не уходит.
     *
     * @template TIntegrationEvent of IntegrationEvent
     * @param class-string<TIntegrationEvent> $expectedEventClass
     * @return TIntegrationEvent
     */
    public function eventOf(string $expectedEventClass): IntegrationEvent
    {
        if (!$this->integrationEvent instanceof $expectedEventClass) {
            throw OutboxMessageLoadingException::restoredTypeMismatch(
                outboxMessage: $this->integrationEvent,
                expectedMessageClass: $expectedEventClass,
            );
        }

        return $this->integrationEvent;
    }
}
