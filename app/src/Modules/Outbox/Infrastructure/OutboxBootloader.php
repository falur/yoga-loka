<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use Spiral\Boot\Bootloader\Bootloader;

final class OutboxBootloader extends Bootloader
{
    protected const BINDINGS = [
        OutboxEventStoreContract::class => OutboxEventStore::class,
        OutboxMessageLoaderContract::class => OutboxMessageLoader::class,
        OutboxRelayContract::class => OutboxRelay::class,
        OutboxRelayLoopControlContract::class => InfiniteOutboxRelayLoopControl::class,
        OutboxRelaySleeperContract::class => SystemOutboxRelaySleeper::class,
        OutboxMessageSerializerContract::class => ValinorOutboxMessageSerializer::class,
        OutboxRelayWorkerContract::class => OutboxRelayWorker::class,
    ];

    protected const SINGLETONS = [
        OutboxJobRegistryContract::class => OutboxJobRegistry::class,
    ];

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: OutboxDebugLogMessage::class,
            outboxJobClass: OutboxDebugLogJob::class,
        );
    }
}
