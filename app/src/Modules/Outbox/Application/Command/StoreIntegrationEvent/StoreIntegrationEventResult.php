<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\StoreIntegrationEvent;

final readonly class StoreIntegrationEventResult
{
    public function __construct(
        public string $outboxEventId,
    ) {}
}
