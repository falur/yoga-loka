<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\RelayOutbox;

use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;

final readonly class RelayOutboxHandler
{
    public function __construct(
        private OutboxRelayWorkerContract $outboxRelayWorker,
    ) {}

    public function handle(RelayOutboxCommand $relayOutboxCommand): RelayOutboxResult
    {
        $outboxRelayBatchSize = OutboxRelayBatchSize::fromInt($relayOutboxCommand->batchSize);

        if ($relayOutboxCommand->loop) {
            $this->outboxRelayWorker->runLoop(
                outboxRelayBatchSize: $outboxRelayBatchSize,
                outboxRelaySleepSeconds: OutboxRelaySleepSeconds::fromInt($relayOutboxCommand->sleepSeconds),
            );
        }

        return new RelayOutboxResult(
            processedCount: $this->outboxRelayWorker->runOnce($outboxRelayBatchSize),
        );
    }
}
