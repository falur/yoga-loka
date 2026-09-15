<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use Cycle\ORM\EntityManagerInterface;

final readonly class OutboxEventStore implements OutboxEventStoreContract
{
    public function __construct(
        private OutboxMessageSerializerContract $outboxMessageSerializer,
        private EntityManagerInterface $entityManager,
    ) {}

    #[\Override]
    public function add(OutboxMessage $outboxMessage): StoredOutboxEventId
    {
        $serializedOutboxMessage = $this->outboxMessageSerializer->serialize($outboxMessage);
        $storedOutboxEvent = StoredOutboxEvent::create(
            type: OutboxEventType::fromString($serializedOutboxMessage->type),
            payload: OutboxEventPayload::fromJson($serializedOutboxMessage->payload),
        );

        $this->entityManager->persist($storedOutboxEvent);

        return StoredOutboxEventId::fromString($storedOutboxEvent->id->value());
    }
}
