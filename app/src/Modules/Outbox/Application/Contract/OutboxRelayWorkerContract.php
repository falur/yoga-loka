<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;

interface OutboxRelayWorkerContract
{
    public function runOnce(OutboxRelayBatchSize $outboxRelayBatchSize): int;

    public function runLoop(OutboxRelayBatchSize $outboxRelayBatchSize, OutboxRelaySleepSeconds $outboxRelaySleepSeconds): never;
}
