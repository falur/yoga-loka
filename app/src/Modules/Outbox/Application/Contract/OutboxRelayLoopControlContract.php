<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

interface OutboxRelayLoopControlContract
{
    public function shouldContinue(): bool;
}
