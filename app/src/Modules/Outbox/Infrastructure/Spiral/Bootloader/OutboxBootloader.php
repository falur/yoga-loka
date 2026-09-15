<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Bootloader;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Infrastructure\Serializer\ValinorOutboxMessageSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventLoaderProvider;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventRoutingProvider;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventStoreProvider;
use App\Modules\Outbox\Infrastructure\Spiral\Registry\OutboxJobRegistry;
use App\Modules\Outbox\Infrastructure\Relay\InfiniteOutboxRelayLoopControl;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelayWorker;
use App\Modules\Outbox\Infrastructure\Relay\SystemOutboxRelaySleeper;
use App\Modules\Outbox\Infrastructure\Spiral\Console\OutboxRelayCommand;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;

final class OutboxBootloader extends Bootloader
{
    protected const BINDINGS = [
        IntegrationEventStoreContract::class => IntegrationEventStoreProvider::class,
        IntegrationEventLoaderContract::class => IntegrationEventLoaderProvider::class,
        IntegrationEventRoutingContract::class => IntegrationEventRoutingProvider::class,
        OutboxRelayContract::class => OutboxRelay::class,
        OutboxRelayLoopControlContract::class => InfiniteOutboxRelayLoopControl::class,
        OutboxRelaySleeperContract::class => SystemOutboxRelaySleeper::class,
        OutboxMessageSerializerContract::class => ValinorOutboxMessageSerializer::class,
        OutboxRelayWorkerContract::class => OutboxRelayWorker::class,
    ];

    protected const SINGLETONS = [
        OutboxJobRegistryContract::class => OutboxJobRegistry::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [ConsoleBootloader::class];
    }

    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(OutboxRelayCommand::class);
    }

    public function boot(IntegrationEventRoutingContract $integrationEventRouting): void
    {
        $integrationEventRouting->register(
            integrationEventClass: OutboxDebugLogRequestedEvent::class,
            jobClass: OutboxDebugLogJob::class,
        );
    }
}
