<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;

interface OutboxRelayContract
{
    public function relay(OutboxRelayBatchSize $outboxRelayBatchSize, \DateTimeImmutable|null $now = null): int;
}
