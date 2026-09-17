<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\Entity;

use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Shared\Domain\Trait\HasTimestamps;

final class StoredOutboxEvent
{
    use HasTimestamps;

    public private(set) OutboxEventId $id;

    public private(set) OutboxEventType $type;

    public private(set) OutboxEventPayload $payload;

    public private(set) OutboxEventStatus $status;

    public private(set) OutboxAttempts $attempts;

    public private(set) OutboxAvailableAt $availableAt;

    public private(set) OutboxEventDate $queuedAt;

    public private(set) OutboxEventDate $handledAt;

    public private(set) OutboxEventDate $failedAt;

    public private(set) OutboxLastError $lastError;

    public static function create(
        OutboxEventType $type,
        OutboxEventPayload $payload,
    ): self {
        $now = new \DateTimeImmutable();

        return self::createAvailableAt(
            type: $type,
            payload: $payload,
            availableAt: $now,
            now: $now,
        );
    }

    public static function createAvailableAt(
        OutboxEventType $type,
        OutboxEventPayload $payload,
        \DateTimeImmutable $availableAt,
        \DateTimeImmutable $now,
    ): self {
        $outboxEvent = new self();
        $outboxEvent->id = OutboxEventId::generate();
        $outboxEvent->type = $type;
        $outboxEvent->payload = $payload;
        $outboxEvent->status = OutboxEventStatus::Pending;
        $outboxEvent->attempts = OutboxAttempts::zero();
        $outboxEvent->availableAt = OutboxAvailableAt::fromDateTime($availableAt);
        $outboxEvent->queuedAt = OutboxEventDate::none();
        $outboxEvent->handledAt = OutboxEventDate::none();
        $outboxEvent->failedAt = OutboxEventDate::none();
        $outboxEvent->lastError = OutboxLastError::none();
        $outboxEvent->initializeTimestamps($now);

        return $outboxEvent;
    }

    public static function restore(
        OutboxEventId $id,
        OutboxEventType $type,
        OutboxEventPayload $payload,
        OutboxEventStatus $status,
        OutboxAttempts $attempts,
        OutboxAvailableAt $availableAt,
        OutboxEventDate $queuedAt,
        OutboxEventDate $handledAt,
        OutboxEventDate $failedAt,
        OutboxLastError $lastError,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $outboxEvent = new self();
        $outboxEvent->id = $id;
        $outboxEvent->type = $type;
        $outboxEvent->payload = $payload;
        $outboxEvent->status = $status;
        $outboxEvent->attempts = $attempts;
        $outboxEvent->availableAt = $availableAt;
        $outboxEvent->queuedAt = $queuedAt;
        $outboxEvent->handledAt = $handledAt;
        $outboxEvent->failedAt = $failedAt;
        $outboxEvent->lastError = $lastError;
        $outboxEvent->createdAt = $createdAt;
        $outboxEvent->updatedAt = $updatedAt;

        return $outboxEvent;
    }

    public function markPublishing(\DateTimeImmutable $availableAt, \DateTimeImmutable $now): void
    {
        $this->status = OutboxEventStatus::Publishing;
        $this->availableAt = OutboxAvailableAt::fromDateTime($availableAt);
        $this->touch($now);
    }

    /**
     * Захват события на публикацию. Для pending-события это штатный первый захват: статус
     * переводится в publishing без инкремента попыток (happy-path не меняется). Если же
     * событие уже в publishing — это повторный захват по истёкшей claim-аренде после жёсткой
     * гибели relay-процесса (OOM/SIGKILL) между markPublishing и markQueued.
     * Такой повторный захват трактуется как очередная попытка: счётчик инкрементируется и
     * сверяется с лимитом тем же контрактом, что обычные попытки публикации (isLastAllowed).
     * Если лимит исчерпан — событие переводится в failed и на публикацию не отдаётся, иначе
     * не упавшее на одном и том же сообщении событие захватывалось бы бесконечно.
     */
    public function claimForPublishing(
        OutboxMaxAttempts $outboxMaxAttempts,
        \DateTimeImmutable $availableAt,
        \DateTimeImmutable $now,
    ): void {
        if ($this->status !== OutboxEventStatus::Publishing) {
            $this->markPublishing(availableAt: $availableAt, now: $now);

            return;
        }

        if ($this->attempts->isLastAllowed($outboxMaxAttempts)) {
            $this->markFailed(
                lastError: OutboxLastError::fromString(
                    'Событие застряло в publishing и исчерпало попытки захвата relay.',
                ),
                outboxMaxAttempts: $outboxMaxAttempts,
                now: $now,
            );

            return;
        }

        $this->attempts = $this->attempts->increment();
        $this->markPublishing(availableAt: $availableAt, now: $now);
    }

    public function markQueued(\DateTimeImmutable $now): void
    {
        $this->status = OutboxEventStatus::Queued;
        $this->queuedAt = $this->queuedAt->isEmpty() ? OutboxEventDate::fromDateTime($now) : $this->queuedAt;
        $this->lastError = OutboxLastError::none();
        $this->touch($now);
    }

    public function markHandled(\DateTimeImmutable $now): void
    {
        $this->status = OutboxEventStatus::Handled;
        $this->queuedAt = $this->queuedAt->isEmpty() ? OutboxEventDate::fromDateTime($now) : $this->queuedAt;
        $this->handledAt = OutboxEventDate::fromDateTime($now);
        $this->touch($now);
    }

    public function recordJobRetry(
        OutboxLastError $lastError,
        OutboxMaxAttempts $outboxMaxAttempts,
        \DateTimeImmutable $now,
    ): void {
        if ($this->attempts->isLastAllowed($outboxMaxAttempts)) {
            $this->markFailed(lastError: $lastError, outboxMaxAttempts: $outboxMaxAttempts, now: $now);

            return;
        }

        $this->status = OutboxEventStatus::Queued;
        $this->queuedAt = $this->queuedAt->isEmpty() ? OutboxEventDate::fromDateTime($now) : $this->queuedAt;
        $this->attempts = $this->attempts->increment();
        $this->lastError = $lastError;
        $this->touch($now);
    }

    public function markFailed(
        OutboxLastError $lastError,
        OutboxMaxAttempts $outboxMaxAttempts,
        \DateTimeImmutable $now,
    ): void {
        $this->status = OutboxEventStatus::Failed;
        $this->attempts = $this->attempts->canIncrement($outboxMaxAttempts)
            ? $this->attempts->increment()
            : $this->attempts;
        $this->lastError = $lastError;
        $this->failedAt = OutboxEventDate::fromDateTime($now);
        $this->touch($now);
    }

    public function recordPublishFailure(
        OutboxLastError $lastError,
        OutboxMaxAttempts $outboxMaxAttempts,
        \DateTimeImmutable $availableAt,
        \DateTimeImmutable $now,
    ): void {
        if ($this->attempts->isLastAllowed($outboxMaxAttempts)) {
            $this->markFailed(lastError: $lastError, outboxMaxAttempts: $outboxMaxAttempts, now: $now);

            return;
        }

        $this->status = OutboxEventStatus::Pending;
        $this->attempts = $this->attempts->increment();
        $this->availableAt = OutboxAvailableAt::fromDateTime($availableAt);
        $this->lastError = $lastError;
        $this->touch($now);
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
