<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\PublicApi;

use App\Modules\Outbox\Application\Query\LoadIntegrationEvent\LoadIntegrationEventHandler;
use App\Modules\Outbox\Application\Query\LoadIntegrationEvent\LoadIntegrationEventQuery;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use GianTiaga\SpiralCqrs\QueryBusInterface;

final readonly class IntegrationEventLoaderProvider implements IntegrationEventLoaderContract
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private LoadIntegrationEventHandler $loadIntegrationEventHandler,
    ) {}

    /**
     * @template TIntegrationEvent of IntegrationEvent
     * @param class-string<TIntegrationEvent> $expectedEventClass
     * @return TIntegrationEvent
     */
    #[\Override]
    public function load(string $outboxEventId, string $expectedEventClass): IntegrationEvent
    {
        return $this->queryBus->dispatch(
            query: new LoadIntegrationEventQuery(
                outboxEventId: $outboxEventId,
                expectedEventClass: $expectedEventClass,
            ),
            handler: $this->loadIntegrationEventHandler->handle(...),
        )->eventOf($expectedEventClass);
    }
}
