<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Repository;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Modules\Outbox\Repository\OutboxPendingRowCollection;
use App\Shared\Infrastructure\Database\DatabaseDateTimeFormat;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Query\SelectQuery;
use Cycle\ORM\EntityManagerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;

final class OutboxEventRepositoryTest extends TestCase
{
    use CleansOutboxEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testStorePersistsAndRestoresOutboxEvent(): void
    {
        $storedOutboxEventId = $this->outboxEventStore()->add(
            new OutboxRepositoryTestMessage(
                text: 'Событие сохранено',
                count: 7,
            ),
        );
        $outboxEventId = OutboxEventId::fromString($storedOutboxEventId->value());

        $this->entityManager()->run();

        $storedOutboxEvent = $this->outboxEventRepository()->findById($outboxEventId);

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
        // queued-статус выставляем тем же CAS-переходом, что и боевой relay: publishing -> queued.
        $queuedEvent->markPublishing(availableAt: $now->modify('-2 minutes'), now: $now);
        $handledEvent->markHandled($now);
        $failedEvent->markFailed(
            lastError: OutboxLastError::fromString('Ошибка доставки'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(100),
            now: $now,
        );

        $this->entityManager()->persist($availablePendingEvent);
        $this->entityManager()->persist($futurePendingEvent);
        $this->entityManager()->persist($expiredPublishingEvent);
        $this->entityManager()->persist($activePublishingEvent);
        $this->entityManager()->persist($queuedEvent);
        $this->entityManager()->persist($handledEvent);
        $this->entityManager()->persist($failedEvent);
        $this->entityManager()->run();

        $this->outboxEventRepository()->markQueuedIfPublishing(outboxEventId: $queuedEvent->id, now: $now);

        $pendingEvents = $this->outboxEventRepository()->findPendingForRelay(
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

    public function testFindFreshStatusByIdBypassesStaleIdentityMapState(): void
    {
        $now = new \DateTimeImmutable('2026-05-25 15:45:00');
        $storedOutboxEvent = $this->createStoredEvent(
            text: 'fresh status',
            availableAt: $now,
            now: $now,
        );
        $storedOutboxEvent->markPublishing(availableAt: $now->modify('+1 minute'), now: $now);
        $this->entityManager()->persist($storedOutboxEvent);
        $this->entityManager()->run();

        self::assertSame(OutboxEventStatus::Publishing, $this->outboxEventRepository()->findById($storedOutboxEvent->id)?->status);

        $handledAt = $now->modify('+10 seconds');
        $this->database()
            ->update('outbox_events')
            ->values([
                'status' => OutboxEventStatus::Handled->value,
                'queued_at' => $handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'handled_at' => $handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'updated_at' => $handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->where('id', $storedOutboxEvent->id->value())
            ->run();

        self::assertSame(OutboxEventStatus::Handled, $this->outboxEventRepository()->findFreshStatusById($storedOutboxEvent->id));
    }

    public function testFindFreshStatusByIdReturnsNullWhenEventIsMissing(): void
    {
        self::assertNull($this->outboxEventRepository()->findFreshStatusById(OutboxEventId::generate()));
    }

    public function testFindFreshStatusByIdRejectsUnexpectedDatabaseRow(): void
    {
        $outboxEventRepository = $this->repositoryWithDatabaseRows([new \stdClass()]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('строку без статуса');

        $outboxEventRepository->findFreshStatusById(OutboxEventId::generate());
    }

    public function testMarkRowFailedByIdReturnsAffectedRowCount(): void
    {
        $now = new \DateTimeImmutable('2026-05-25 15:50:00');
        $pendingEvent = $this->createStoredEvent(text: 'mark failed pending', availableAt: $now, now: $now);
        $this->entityManager()->persist($pendingEvent);
        $this->entityManager()->run();

        // Совпадающий id в pending: CAS реально переводит строку в failed → 1 затронутая строка.
        self::assertSame(1, $this->outboxEventRepository()->markRowFailedById(
            outboxEventId: $pendingEvent->id->value(),
            lastError: OutboxLastError::fromString('повреждённые данные'),
            now: $now,
        ));
        self::assertSame(OutboxEventStatus::Failed, $this->outboxEventRepository()->findFreshStatusById($pendingEvent->id));

        // Повторный CAS по той же строке: она уже failed (вне pending/publishing) → 0 затронутых строк.
        // Это и есть «нулевой прогресс», который recovery в relay не должен считать прогрессом.
        self::assertSame(0, $this->outboxEventRepository()->markRowFailedById(
            outboxEventId: $pendingEvent->id->value(),
            lastError: OutboxLastError::fromString('повторная пометка'),
            now: $now,
        ));

        // Несовпадающий id: ни одной строки не матчит → 0 затронутых строк.
        self::assertSame(0, $this->outboxEventRepository()->markRowFailedById(
            outboxEventId: OutboxEventId::generate()->value(),
            lastError: OutboxLastError::fromString('несуществующая строка'),
            now: $now,
        ));
    }

    public function testPendingForRelayRowsSkipUnexpectedDatabaseRow(): void
    {
        $pendingRows = $this->repositoryWithDatabaseRows([new \stdClass()])->pendingForRelayRows(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2026-06-06 14:15:00'),
        );

        self::assertInstanceOf(OutboxPendingRowCollection::class, $pendingRows);
        self::assertTrue($pendingRows->isEmpty());
    }

    public function testPendingForRelayRowsLimitsResultByBatchSize(): void
    {
        $now = new \DateTimeImmutable('2026-06-06 14:20:00');

        foreach (\range(1, 5) as $index) {
            $this->entityManager()->persist($this->createStoredEvent(
                text: \sprintf('pending %d', $index),
                availableAt: $now->modify('-1 minute'),
                now: $now,
            ));
        }
        $this->entityManager()->run();

        $pendingRows = $this->outboxEventRepository()->pendingForRelayRows(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(2),
            now: $now,
        );

        self::assertInstanceOf(OutboxPendingRowCollection::class, $pendingRows);
        self::assertCount(2, $pendingRows);
    }

    public function testStoredOutboxEventsRejectsUnexpectedObject(): void
    {
        $storedOutboxEvents = new \ReflectionMethod(OutboxEventRepository::class, 'storedOutboxEvents');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('неверного типа');

        $storedOutboxEvents->invoke($this->outboxEventRepository(), [new \stdClass()]);
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

    private function database(): DatabaseInterface
    {
        return $this->getContainer()->get(DatabaseInterface::class);
    }

    private function outboxEventRepository(): OutboxEventRepository
    {
        return $this->getContainer()->get(OutboxEventRepository::class);
    }

    private function outboxEventStore(): OutboxEventStoreContract
    {
        return $this->getContainer()->get(OutboxEventStoreContract::class);
    }

    private function outboxMessageSerializer(): OutboxMessageSerializerContract
    {
        return $this->getContainer()->get(OutboxMessageSerializerContract::class);
    }

    /**
     * @param list<object> $databaseRows
     */
    private function repositoryWithDatabaseRows(array $databaseRows): OutboxEventRepository
    {
        $selectQuery = $this->createStub(SelectQuery::class);
        $selectQuery->method('from')->willReturnSelf();
        $selectQuery->method('where')->willReturnSelf();
        $selectQuery->method('limit')->willReturnSelf();
        $selectQuery->method('orderBy')->willReturnSelf();
        $selectQuery->method('fetchAll')->willReturn($databaseRows);

        $database = $this->createStub(DatabaseInterface::class);
        $database->method('select')->willReturn($selectQuery);

        $outboxEventRepository = (new \ReflectionClass(OutboxEventRepository::class))->newInstanceWithoutConstructor();
        $databaseProperty = new \ReflectionProperty(OutboxEventRepository::class, 'database');
        $databaseProperty->setValue($outboxEventRepository, $database);

        return $outboxEventRepository;
    }
}

final readonly class OutboxRepositoryTestMessage implements OutboxMessage
{
    public function __construct(
        public string $text,
        public int $count,
    ) {}
}
