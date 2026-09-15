<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Registry;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Infrastructure\Exception\OutboxJobRegistryException;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use Spiral\Queue\HandlerInterface;

final class OutboxJobRegistry implements OutboxJobRegistryContract
{
    /**
     * @var array<class-string<IntegrationEvent>, class-string<HandlerInterface>>
     */
    private array $jobsByEventClass = [];

    /**
     * @param class-string $integrationEventClass
     * @param class-string $jobClass
     */
    #[\Override]
    public function register(string $integrationEventClass, string $jobClass): void
    {
        if (!\is_subclass_of(object_or_class: $integrationEventClass, class: IntegrationEvent::class)) {
            throw OutboxJobRegistryException::invalidMessageClass($integrationEventClass);
        }

        if (!\is_subclass_of(object_or_class: $jobClass, class: HandlerInterface::class)) {
            throw OutboxJobRegistryException::invalidJobClass($jobClass);
        }

        $this->jobsByEventClass[$integrationEventClass] = $jobClass;
    }

    /**
     * @return class-string<HandlerInterface>
     */
    #[\Override]
    public function jobFor(IntegrationEvent $integrationEvent): string
    {
        $integrationEventClass = $integrationEvent::class;

        return $this->jobsByEventClass[$integrationEventClass]
            ?? throw OutboxJobRegistryException::jobNotRegistered($integrationEventClass);
    }
}
