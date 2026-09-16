<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Repository;

use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Application\Contract\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class StoredOutboxEventRepositoryTest extends DatabaseTestCase
{
    public function testStorePersistsAndRestoresOutboxEvent(): void
    {
        $storedOutboxEventId = $this->outboxEventStore()->add(
            new OutboxRepositoryTestMessage(
                text: 'Событие сохранено',
                count: 7,
            ),
        );
        $outboxEventId = OutboxEventId::fromString($storedOutboxEventId);

        $this->entityManager()->run();

        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertInstanceOf(StoredOutboxEvent::class, $storedOutboxEvent);
        self::assertTrue($outboxEventId->equals($storedOutboxEvent->id));
        self::assertSame(OutboxEventStatus::Pending, $storedOutboxEvent->status);
        self::assertTrue($storedOutboxEvent->lastError->isEmpty());

        $outboxMessage = $this->outboxMessageSerializer()->deserialize(
            serializedOutboxMessage: new SerializedOutboxMessage(
                type: $storedOutboxEvent->type->value(),
                payload: $storedOutboxEvent->payload->value(),
            ),
        );

        self::assertInstanceOf(OutboxRepositoryTestMessage::class, $outboxMessage);
        self::assertSame('Событие сохранено', $outboxMessage->text);
        self::assertSame(7, $outboxMessage->count);
    }

    public function testFindPendingForRelayReturnsOnlyAvailablePendingAndExpiredPublishingEvents(): void
    {
        $now = new \DateTimeImmutable('2026-05-25 15:37:00');
        $availablePendingEvent = $this->createStoredEvent(
            text: 'available pending',
            availableAt: $now->modify('-1 minute'),
            now: $now,
        );
        $futurePendingEvent = $this->createStoredEvent(
            text: 'future pending',
            availableAt: $now->modify('+1 minute'),
            now: $now,
        );
        $expiredPublishingEvent = $this->createStoredEvent(
            text: 'expired publishing',
            availableAt: $now->modify('-2 minutes'),
            now: $now,
        );
        $activePublishingEvent = $this->createStoredEvent(
            text: 'active publishing',
            availableAt: $now->modify('-2 minutes'),
            now: $now,
        );
        $queuedEvent = $this->createStoredEvent(text: 'queued', availableAt: $now->modify('-2 minutes'), now: $now);
        $handledEvent = $this->createStoredEvent(text: 'handled', availableAt: $now->modify('-2 minutes'), now: $now);
        $failedEvent = $this->createStoredEvent(text: 'failed', availableAt: $now->modify('-2 minutes'), now: $now);

        $expiredPublishingEvent->markPublishing(availableAt: $now->modify('-1 minute'), now: $now);
        $activePublishingEvent->markPublishing(availableAt: $now->modify('+1 minute'), now: $now);
        // queued-статус проводим тем же путём, что и боевой relay: publishing -> queued, через Entity.
        $queuedEvent->markPublishing(availableAt: $now->modify('-2 minutes'), now: $now);
        $queuedEvent->markQueued($now);
        $handledEvent->markHandled($now);
        $failedEvent->markFailed(
            lastError: OutboxLastError::fromString('Ошибка доставки'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(100),
            now: $now,
        );

        // StoredOutboxEvent — чистая доменная сущность без Cycle-разметки, поэтому в отличие
        // от прежнего прямого $entityManager->persist() сохраняется через доменный saveAll().
        $this->storedOutboxEventRepository()->saveAll(new OutboxEventCollection([
            $availablePendingEvent,
            $futurePendingEvent,
            $expiredPublishingEvent,
            $activePublishingEvent,
            $queuedEvent,
            $handledEvent,
            $failedEvent,
        ]));

        $pendingEvents = $this->storedOutboxEventRepository()->findPendingForRelay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );

        self::assertInstanceOf(OutboxEventCollection::class, $pendingEvents);
        self::assertTrue($this->collectionContainsId($pendingEvents, $availablePendingEvent->id));
        self::assertTrue($this->collectionContainsId($pendingEvents, $expiredPublishingEvent->id));
        self::assertFalse($this->collectionContainsId($pendingEvents, $futurePendingEvent->id));
        self::assertFalse($this->collectionContainsId($pendingEvents, $activePublishingEvent->id));
        self::assertFalse($this->collectionContainsId($pendingEvents, $queuedEvent->id));
        self::assertFalse($this->collectionContainsId($pendingEvents, $handledEvent->id));
        self::assertFalse($this->collectionContainsId($pendingEvents, $failedEvent->id));
        self::assertSame(
            $pendingEvents->pluck('id')->map(static fn(OutboxEventId $outboxEventId): string => $outboxEventId->value())->sort()->values()->all(),
            $pendingEvents->pluck('id')->map(static fn(OutboxEventId $outboxEventId): string => $outboxEventId->value())->values()->all(),
        );
    }

    private function createStoredEvent(
        string $text,
        \DateTimeImmutable $availableAt,
        \DateTimeImmutable $now,
    ): StoredOutboxEvent {
        return StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(OutboxRepositoryTestMessage::class),
            payload: OutboxEventPayload::fromJson(\sprintf('{"text":"%s","count":1}', $text)),
            availableAt: $availableAt,
            now: $now,
        );
    }

    private function collectionContainsId(OutboxEventCollection $outboxEvents, OutboxEventId $outboxEventId): bool
    {
        return $outboxEvents->contains(
            static fn(StoredOutboxEvent $storedOutboxEvent): bool => $storedOutboxEvent->id->equals($outboxEventId),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function storedOutboxEventRepository(): StoredOutboxEventRepository
    {
        return $this->getContainer()->get(StoredOutboxEventRepository::class);
    }

    private function outboxEventStore(): IntegrationEventStoreContract
    {
        return $this->getContainer()->get(IntegrationEventStoreContract::class);
    }

    private function outboxMessageSerializer(): OutboxMessageSerializerContract
    {
        return $this->getContainer()->get(OutboxMessageSerializerContract::class);
    }
}

final readonly class OutboxRepositoryTestMessage implements IntegrationEvent
{
    public function __construct(
        public string $text,
        public int $count,
    ) {}
}
