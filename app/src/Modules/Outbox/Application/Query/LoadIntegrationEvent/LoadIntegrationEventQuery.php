<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Query\LoadIntegrationEvent;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final readonly class LoadIntegrationEventQuery
{
    /**
     * @param class-string<IntegrationEvent> $expectedEventClass
     */
    public function __construct(
        public string $outboxEventId,
        public string $expectedEventClass,
    ) {}
}
