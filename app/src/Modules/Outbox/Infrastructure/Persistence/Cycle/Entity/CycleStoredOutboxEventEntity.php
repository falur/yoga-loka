<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Columns\StoredOutboxEventColumns;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Repository\CycleStoredOutboxEventRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'outbox_event',
    table: StoredOutboxEventColumns::TABLE,
    repository: CycleStoredOutboxEventRepository::class,
    typecast: [Typecast::class],
)]
final class CycleStoredOutboxEventEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: StoredOutboxEventColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(255)', name: StoredOutboxEventColumns::TYPE)]
    public string $type;

    #[Column(type: 'jsonb', name: StoredOutboxEventColumns::PAYLOAD)]
    public string $payload;

    #[Column(type: 'string(32)', name: StoredOutboxEventColumns::STATUS, typecast: OutboxEventStatus::class)]
    public OutboxEventStatus $status;

    #[Column(type: 'integer', name: StoredOutboxEventColumns::ATTEMPTS)]
    public int $attempts;

    #[Column(type: 'datetime', name: StoredOutboxEventColumns::AVAILABLE_AT, typecast: 'datetime')]
    public \DateTimeImmutable $availableAt;

    #[Column(type: 'datetime', name: StoredOutboxEventColumns::QUEUED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $queuedAt;

    #[Column(type: 'datetime', name: StoredOutboxEventColumns::HANDLED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $handledAt;

    #[Column(type: 'datetime', name: StoredOutboxEventColumns::FAILED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $failedAt;

    #[Column(type: 'text', name: StoredOutboxEventColumns::LAST_ERROR, nullable: true)]
    public string|null $lastError;
}
