<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;

interface OutboxMessageSerializerContract
{
    public function serialize(OutboxMessage $outboxMessage): SerializedOutboxMessage;

    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): OutboxMessage;
}
