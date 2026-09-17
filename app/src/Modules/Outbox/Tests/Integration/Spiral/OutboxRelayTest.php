<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use Tests\Support\Outbox\CleansOutboxEvents;
use App\Modules\Outbox\Tests\Integration\Spiral\FailingOutboxRelayJob;
use App\Modules\Outbox\Tests\Integration\Spiral\FailingOutboxRelayMessage;
use App\Modules\Outbox\Tests\Integration\Spiral\OutboxRelayTestHelpers;
use App\Modules\Outbox\Tests\Integration\Spiral\RetryingOutboxRelayJob;
use App\Modules\Outbox\Tests\Integration\Spiral\RetryingOutboxRelayMessage;
use Tests\TestCase;

final class OutboxRelayTest extends TestCase
{
    use CleansOutboxEvents;
    use OutboxRelayTestHelpers;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testRelayWithSyncConnectionRunsJobWithEnvelopeAndKeepsHandledStatus(): void
    {
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'sync relay check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:07:00'),
            ),
        );
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:08:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(1, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->queuedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testRelayDoesNotOverwriteFailedStatusWhenSyncJobFails(): void
    {
        $this->registerOutboxJob(
            integrationEventClass: FailingOutboxRelayMessage::class,
            jobClass: FailingOutboxRelayJob::class,
        );
        $outboxEventId = $this->addOutboxMessage(new FailingOutboxRelayMessage(reason: 'sync failure'));
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:15:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }

    public function testRelayDoesNotOverwriteQueuedStatusWhenSyncJobRetries(): void
    {
        $this->registerOutboxJob(
            integrationEventClass: RetryingOutboxRelayMessage::class,
            jobClass: RetryingOutboxRelayJob::class,
        );
        $outboxEventId = $this->addOutboxMessage(new RetryingOutboxRelayMessage(reason: 'sync retry'));
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:16:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status);
        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertTrue($storedOutboxEvent->failedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }
}
