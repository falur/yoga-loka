<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Registry;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Exception\OutboxJobRegistryException;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\QueueRegistry;

/**
 * Единственный источник истины пары «outbox-событие -> Job»: вызов {@see self::register()} (уже
 * вызывается каждым производящим событие модулем в его `boot()` через
 * {@see \App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract}) одновременно
 * запоминает маршрут для relay и регистрирует Job в реестре очереди Spiral
 * ({@see QueueRegistry::setHandler()}/{@see QueueRegistry::setSerializer()}), поэтому
 * `app/config/queue.php` не перечисляет Job-классы статично.
 *
 * Регистрация идёт в реестр, а не патчем секции конфигурации 'queue': секцию необратимо забирает
 * себе (createInjection) уже `Spiral\Queue\Bootloader\QueueBootloader::boot()`, который резолвит
 * JobHandlerLocatorListener/SerializerLocatorListener, поэтому патч из прикладного `boot()` падал бы
 * с ConfigDeliveredException. Реестр же остаётся изменяемым и после старта — благодаря этому пара
 * «событие -> Job» регистрируется одинаково и на bootload, и в рантайме (например, в тесте).
 */
final class OutboxJobRegistry implements OutboxJobRegistryContract
{
    /**
     * @var array<class-string<IntegrationEvent>, class-string<HandlerInterface>>
     */
    private array $jobsByEventClass = [];

    public function __construct(
        private readonly QueueRegistry $queueRegistry,
        private readonly OutboxQueueSerializer $outboxQueueSerializer,
    ) {}

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

        $this->queueRegistry->setHandler(jobType: $jobClass, handler: $jobClass);
        $this->queueRegistry->setSerializer(jobType: $jobClass, serializer: $this->outboxQueueSerializer);
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
