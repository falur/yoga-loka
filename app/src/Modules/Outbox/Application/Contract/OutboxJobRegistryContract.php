<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

interface OutboxJobRegistryContract
{
    /**
     * @param class-string<IntegrationEvent> $integrationEventClass
     * @param class-string $jobClass
     */
    public function register(string $integrationEventClass, string $jobClass): void;

    /**
     * @return class-string
     */
    public function jobFor(IntegrationEvent $integrationEvent): string;
}
