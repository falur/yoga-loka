<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;

interface OutboxEventStoreContract
{
    public function add(OutboxMessage $outboxMessage): StoredOutboxEventId;
}
