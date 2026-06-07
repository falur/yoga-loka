<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;

interface OutboxRelaySleeperContract
{
    public function sleep(OutboxRelaySleepSeconds $seconds): void;
}
