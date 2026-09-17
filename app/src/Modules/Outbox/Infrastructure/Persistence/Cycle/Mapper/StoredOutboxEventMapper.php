<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Entity\CycleStoredOutboxEventEntity;

final readonly class StoredOutboxEventMapper
{
    public function toDomain(CycleStoredOutboxEventEntity $cycleEntity): StoredOutboxEvent
    {
        return StoredOutboxEvent::restore(
            id: OutboxEventId::fromString($cycleEntity->id),
            type: OutboxEventType::fromString($cycleEntity->type),
            payload: OutboxEventPayload::fromJson($cycleEntity->payload),
            status: $cycleEntity->status,
            attempts: OutboxAttempts::fromInt($cycleEntity->attempts),
            availableAt: OutboxAvailableAt::fromDateTime($cycleEntity->availableAt),
            queuedAt: $this->outboxEventDate($cycleEntity->queuedAt),
            handledAt: $this->outboxEventDate($cycleEntity->handledAt),
            failedAt: $this->outboxEventDate($cycleEntity->failedAt),
            lastError: $this->outboxLastError($cycleEntity->lastError),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        StoredOutboxEvent $storedOutboxEvent,
        CycleStoredOutboxEventEntity|null $cycleEntity = null,
    ): CycleStoredOutboxEventEntity {
        $cycleEntity ??= new CycleStoredOutboxEventEntity();
        $cycleEntity->id = $storedOutboxEvent->id->value();
        $cycleEntity->type = $storedOutboxEvent->type->value();
        $cycleEntity->payload = $storedOutboxEvent->payload->value();
        $cycleEntity->status = $storedOutboxEvent->status;
        $cycleEntity->attempts = $storedOutboxEvent->attempts->value();
        $cycleEntity->availableAt = $storedOutboxEvent->availableAt->value();
        $cycleEntity->queuedAt = $storedOutboxEvent->queuedAt->value();
        $cycleEntity->handledAt = $storedOutboxEvent->handledAt->value();
        $cycleEntity->failedAt = $storedOutboxEvent->failedAt->value();
        $cycleEntity->lastError = $storedOutboxEvent->lastError->toDatabaseValue();
        $cycleEntity->createdAt = $storedOutboxEvent->createdAt;
        $cycleEntity->updatedAt = $storedOutboxEvent->updatedAt;

        return $cycleEntity;
    }

    /**
     * Null-bridging для трёх nullable-колонок перехода (queued_at/handled_at/failed_at):
     * логика перенесена без изменений из удалённого OutboxEventDateTypecast::castDatabaseValue().
     */
    private function outboxEventDate(\DateTimeImmutable|null $value): OutboxEventDate
    {
        return $value === null ? OutboxEventDate::none() : OutboxEventDate::fromDateTime($value);
    }

    /**
     * NULL и пустая строка трактуются одинаково — как «ошибки нет». Логика перенесена без
     * изменений из удалённого OutboxLastErrorTypecast::castDatabaseValue().
     */
    private function outboxLastError(string|null $value): OutboxLastError
    {
        if ($value === null || \trim($value) === '') {
            return OutboxLastError::none();
        }

        return OutboxLastError::fromString($value);
    }
}
