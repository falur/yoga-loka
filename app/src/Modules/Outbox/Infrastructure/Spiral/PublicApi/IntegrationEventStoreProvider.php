<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\PublicApi;

use App\Modules\Outbox\Application\Command\StoreIntegrationEvent\StoreIntegrationEventCommand;
use App\Modules\Outbox\Application\Command\StoreIntegrationEvent\StoreIntegrationEventHandler;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use GianTiaga\SpiralCqrs\CommandBusInterface;

final readonly class IntegrationEventStoreProvider implements IntegrationEventStoreContract
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StoreIntegrationEventHandler $storeIntegrationEventHandler,
    ) {}

    #[\Override]
    public function add(IntegrationEvent $integrationEvent): string
    {
        return $this->commandBus->dispatch(
            command: new StoreIntegrationEventCommand(integrationEvent: $integrationEvent),
            handler: $this->storeIntegrationEventHandler->handle(...),
        )->outboxEventId;
    }
}
