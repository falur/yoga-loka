<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;

final readonly class InfiniteOutboxRelayLoopControl implements OutboxRelayLoopControlContract
{
    #[\Override]
    public function shouldContinue(): bool
    {
        return true;
    }
}
