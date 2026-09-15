<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\StoreIntegrationEvent;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final readonly class StoreIntegrationEventCommand
{
    public function __construct(
        public IntegrationEvent $integrationEvent,
    ) {}
}
