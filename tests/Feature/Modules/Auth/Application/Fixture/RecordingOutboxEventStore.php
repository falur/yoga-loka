<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application\Fixture;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;

/**
 * Записывающий outbox-стор для проверки, какие сообщения поставлены в очередь.
 */
final class RecordingOutboxEventStore implements OutboxEventStoreContract
{
    /**
     * @var list<OutboxMessage>
     */
    public array $messages = [];

    #[\Override]
    public function add(OutboxMessage $outboxMessage): StoredOutboxEventId
    {
        $this->messages[] = $outboxMessage;

        return StoredOutboxEventId::fromString('outbox-test');
    }
}
