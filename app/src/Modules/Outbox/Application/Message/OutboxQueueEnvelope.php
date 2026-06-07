<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Message;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;

final readonly class OutboxQueueEnvelope
{
    public function __construct(
        public OutboxEventId $outboxEventId,
        public OutboxEventType $outboxEventType,
    ) {}

    public static function fromStoredEvent(StoredOutboxEvent $storedOutboxEvent): self
    {
        return new self(
            outboxEventId: $storedOutboxEvent->id,
            outboxEventType: $storedOutboxEvent->type,
        );
    }
}
