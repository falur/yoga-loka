<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Cycle;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Mapper\StoredOutboxEventMapper;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки четырёх удалённых typecast-классов (OutboxAvailableAtTypecast,
 * OutboxEventDateTypecast, OutboxEventPayloadTypecast, OutboxLastErrorTypecast) на Mapper,
 * куда переехала их логика: все четыре — простые VO-колонки (правило переноса значений
 * колонок волны E), включая null-bridging между nullable-колонками (queued_at/handled_at/
 * failed_at/last_error) и non-null доменными VO с null-object (OutboxEventDate::none(),
 * OutboxLastError::none()).
 */
final class StoredOutboxEventMapperTest extends TestCase
{
    public function testMapsFreshPendingEventToAndFromCycleEntity(): void
    {
        $mapper = new StoredOutboxEventMapper();
        $now = new \DateTimeImmutable('2026-05-25 15:37:00');

        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(StoredOutboxEventMapperTestMessage::class),
            payload: OutboxEventPayload::fromJson('{"text":"ok"}'),
            availableAt: $now,
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($storedOutboxEvent);

        self::assertSame($storedOutboxEvent->id->value(), $cycleEntity->id);
        self::assertSame(StoredOutboxEventMapperTestMessage::class, $cycleEntity->type);
        self::assertSame('{"text":"ok"}', $cycleEntity->payload);
        self::assertSame(OutboxEventStatus::Pending, $cycleEntity->status);
        self::assertSame(0, $cycleEntity->attempts);
        self::assertSame($now, $cycleEntity->availableAt);
        self::assertNull($cycleEntity->queuedAt);
        self::assertNull($cycleEntity->handledAt);
        self::assertNull($cycleEntity->failedAt);
        self::assertNull($cycleEntity->lastError);
        self::assertSame($now, $cycleEntity->createdAt);
        self::assertSame($now, $cycleEntity->updatedAt);

        $restoredEvent = $mapper->toDomain($cycleEntity);

        self::assertTrue($storedOutboxEvent->id->equals($restoredEvent->id));
        self::assertTrue($storedOutboxEvent->type->equals($restoredEvent->type));
        self::assertTrue($storedOutboxEvent->payload->equals($restoredEvent->payload));
        self::assertSame(OutboxEventStatus::Pending, $restoredEvent->status);
        self::assertSame(0, $restoredEvent->attempts->value());
        self::assertTrue($restoredEvent->availableAt->equals($storedOutboxEvent->availableAt));
        self::assertTrue($restoredEvent->queuedAt->isEmpty());
        self::assertTrue($restoredEvent->handledAt->isEmpty());
        self::assertTrue($restoredEvent->failedAt->isEmpty());
        self::assertTrue($restoredEvent->lastError->isEmpty());
    }

    public function testMapsEventWithAllTransitionDatesAndError(): void
    {
        $mapper = new StoredOutboxEventMapper();
        $now = new \DateTimeImmutable('2026-05-25T16:06:00+00:00');

        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(StoredOutboxEventMapperTestMessage::class),
            payload: OutboxEventPayload::fromJson('{"text":"ok"}'),
            availableAt: $now,
            now: $now,
        );
        $storedOutboxEvent->markPublishing(availableAt: $now, now: $now);
        $storedOutboxEvent->markQueued($now);
        $storedOutboxEvent->markFailed(
            lastError: OutboxLastError::fromString('Ошибка доставки'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(100),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($storedOutboxEvent);

        self::assertSame($now, $cycleEntity->queuedAt);
        self::assertSame($now, $cycleEntity->failedAt);
        self::assertNull($cycleEntity->handledAt);
        self::assertSame('Ошибка доставки', $cycleEntity->lastError);

        $restoredEvent = $mapper->toDomain($cycleEntity);

        self::assertFalse($restoredEvent->queuedAt->isEmpty());
        self::assertSame($now, $restoredEvent->queuedAt->value());
        self::assertFalse($restoredEvent->failedAt->isEmpty());
        self::assertSame($now, $restoredEvent->failedAt->value());
        self::assertTrue($restoredEvent->handledAt->isEmpty());
        self::assertSame('Ошибка доставки', $restoredEvent->lastError->value());
    }

    /**
     * NULL и пустая/пробельная строка в last_error трактуются одинаково — «ошибки нет».
     * Гидрация устойчива к ручным правкам и импортам, где в колонку попала пустая строка.
     */
    public function testTreatsBlankLastErrorColumnAsNoError(): void
    {
        $mapper = new StoredOutboxEventMapper();
        $now = new \DateTimeImmutable('2026-05-25 15:37:00');

        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(StoredOutboxEventMapperTestMessage::class),
            payload: OutboxEventPayload::fromJson('{"text":"ok"}'),
            availableAt: $now,
            now: $now,
        );
        $cycleEntity = $mapper->toCycleEntity($storedOutboxEvent);

        $cycleEntity->lastError = '   ';
        self::assertTrue($mapper->toDomain($cycleEntity)->lastError->isEmpty());

        $cycleEntity->lastError = '';
        self::assertTrue($mapper->toDomain($cycleEntity)->lastError->isEmpty());

        $cycleEntity->lastError = null;
        self::assertTrue($mapper->toDomain($cycleEntity)->lastError->isEmpty());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new StoredOutboxEventMapper();
        $now = new \DateTimeImmutable('2026-05-25 15:37:00');

        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(StoredOutboxEventMapperTestMessage::class),
            payload: OutboxEventPayload::fromJson('{"text":"ok"}'),
            availableAt: $now,
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($storedOutboxEvent);
        $storedOutboxEvent->markPublishing(availableAt: $now, now: $now);
        $updatedCycleEntity = $mapper->toCycleEntity($storedOutboxEvent, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame(OutboxEventStatus::Publishing, $updatedCycleEntity->status);
    }
}

final readonly class StoredOutboxEventMapperTestMessage implements IntegrationEvent
{
    public function __construct(
        public string $text,
    ) {}
}
