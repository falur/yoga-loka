<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Contract;

interface IntegrationEventRoutingContract
{
    /**
     * Объявляет маршрут «событие -> Job»: по классу события relay находит Job потребителя.
     *
     * @param class-string<IntegrationEvent> $integrationEventClass
     * @param class-string $jobClass
     */
    public function register(string $integrationEventClass, string $jobClass): void;
}
