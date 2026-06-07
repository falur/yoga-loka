<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;

interface OutboxMessageLoaderContract
{
    /**
     * @template TOutboxMessage of OutboxMessage
     * @param class-string<TOutboxMessage> $expectedMessageClass
     * @return TOutboxMessage
     */
    public function load(OutboxEventId $outboxEventId, string $expectedMessageClass): OutboxMessage;
}
