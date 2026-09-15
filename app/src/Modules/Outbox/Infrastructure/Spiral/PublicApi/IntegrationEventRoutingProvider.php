<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\PublicApi;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;

final readonly class IntegrationEventRoutingProvider implements IntegrationEventRoutingContract
{
    public function __construct(
        private OutboxJobRegistryContract $outboxJobRegistry,
    ) {}

    #[\Override]
    public function register(string $integrationEventClass, string $jobClass): void
    {
        $this->outboxJobRegistry->register(
            integrationEventClass: $integrationEventClass,
            jobClass: $jobClass,
        );
    }
}
