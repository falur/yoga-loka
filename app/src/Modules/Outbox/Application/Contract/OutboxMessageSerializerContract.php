<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Application\Dto\SerializedOutboxMessage;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

interface OutboxMessageSerializerContract
{
    public function serialize(IntegrationEvent $integrationEvent): SerializedOutboxMessage;

    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): IntegrationEvent;
}
