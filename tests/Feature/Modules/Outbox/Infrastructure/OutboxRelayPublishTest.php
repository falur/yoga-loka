<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueHeaders;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueuePublisher;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Shared\Infrastructure\Spiral\Configuration\Outbox\OutboxConfig;
use App\Shared\Infrastructure\Persistence\Cycle\DatabaseDateTimeFormat;
use Cycle\Database\DatabaseInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\DeleteEventDuringPushQueueConnectionProvider;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\MarkHandledDuringPushQueueConnectionProvider;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\OutboxRelayTestHelpers;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RecordingOutboxLogger;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\ThrowingQueueConnectionProvider;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\UnregisteredOutboxRelayMessage;
use Tests\TestCase;

final class OutboxRelayPublishTest extends TestCase
{
    use CleansOutboxEvents;
    use OutboxRelayTestHelpers;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testRelayPushesJobWithHeadersAndMarksEventQueued(): void
    {
        $this->useQueueConnection('rabbitmq');
        $fakeQueue = $this->fakeQueue()->getConnection();
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:01:00'),
            ),
        );
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:02:00'),
        );

        self::assertSame(1, $publishedCount);

        $fakeQueue->assertPushed(OutboxDebugLogJob::class, static function (array $job) use ($outboxEventId): bool {
            $payload = $job['payload'];
            $options = $job['options'];

            return \is_array($payload)
                && (
                    ($payload['outboxId'] ?? null) === $outboxEventId->value()
                    && ($payload['outboxType'] ?? null) === OutboxDebugLogRequestedEvent::class
                )
                && $options instanceof OptionsInterface
                && $options->getHeaderLine(OutboxQueueHeaders::OUTBOX_ID) === $outboxEventId->value()
                && $options->getHeaderLine(OutboxQueueHeaders::OUTBOX_TYPE) === OutboxDebugLogRequestedEvent::class;
        });

        self::assertSame(OutboxEventStatus::Queued->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($this->outboxQueuedAtIsFilledInDatabase($outboxEventId));
    }

    public function testRelayDoesNotOverwriteHandledStatusWhenWorkerFinishesDuringPush(): void
    {
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay race check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:13:00'),
            ),
        );
        $this->entityManager()->run();

        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new MarkHandledDuringPushQueueConnectionProvider(
                storedOutboxEventRepository: $this->storedOutboxEventRepository(),
                outboxEventId: $outboxEventId,
                handledAt: new \DateTimeImmutable('2026-05-25 16:14:00'),
            ),
        );

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:14:00'),
        );

        self::assertSame(1, $publishedCount);
        self::assertSame(OutboxEventStatus::Handled->value, $this->outboxStatusInDatabase($outboxEventId));
    }

    /**
     * Строка события может исчезнуть между захватом и возвратом из push. Задача при этом уже
     * ушла в очередь, поэтому relay опирается на снимок, прочитанный при захвате: он не падает
     * и доводит событие до queued, чтобы взявший задачу worker нашёл своё событие на месте.
     */
    public function testRelaySurvivesEventDeletedDuringSuccessfulPush(): void
    {
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay vanished row',
                createdAt: new \DateTimeImmutable('2026-05-25 16:21:00'),
            ),
        );
        $this->entityManager()->run();

        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new DeleteEventDuringPushQueueConnectionProvider(
                database: $this->database(),
                outboxEventId: $outboxEventId,
            ),
        );

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:22:00'),
        );

        self::assertSame(1, $publishedCount);
        self::assertSame(OutboxEventStatus::Queued->value, $this->outboxStatusInDatabase($outboxEventId));
    }

    /**
     * То же исчезновение строки, но push при этом ещё и падает: relay записывает ошибку
     * публикации в снимок и не теряет событие — оно остаётся видимым как pending с ошибкой.
     */
    public function testRelaySurvivesEventDeletedDuringFailedPush(): void
    {
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay vanished row on failure',
                createdAt: new \DateTimeImmutable('2026-05-25 16:23:00'),
            ),
        );
        $this->entityManager()->run();

        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new DeleteEventDuringPushQueueConnectionProvider(
                database: $this->database(),
                outboxEventId: $outboxEventId,
                exception: new \RuntimeException('Очередь недоступна.'),
            ),
        );

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:24:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Pending->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($this->outboxLastErrorIsFilledInDatabase($outboxEventId));
    }

    public function testRelayKeepsEventPendingWhenQueuePushFails(): void
    {
        $this->useQueueConnection('rabbitmq');
        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new ThrowingQueueConnectionProvider(),
        );

        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay failure',
                createdAt: new \DateTimeImmutable('2026-05-25 16:03:00'),
            ),
        );
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:04:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Pending, $storedOutboxEvent->status);
        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }

    public function testRelayLogsPublishFailureThatStaysPendingAsWarning(): void
    {
        $this->useQueueConnection('rabbitmq');
        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new ThrowingQueueConnectionProvider(),
        );

        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay warn failure',
                createdAt: new \DateTimeImmutable('2026-05-25 16:03:00'),
            ),
        );
        $this->entityManager()->run();

        $recordingOutboxLogger = new RecordingOutboxLogger();
        $publishedCount = $this->relayWithLogger($recordingOutboxLogger)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:04:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Pending->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'warning',
            messageSubstring: 'не смог поставить событие в очередь',
        ));
        self::assertFalse($recordingOutboxLogger->hasRecord(
            level: 'error',
            messageSubstring: 'окончательно перевёл событие в failed',
        ));
    }

    public function testRelayLogsFinalPublishFailureAsError(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(1));
        $this->useQueueConnection('rabbitmq');
        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new ThrowingQueueConnectionProvider(),
        );

        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'relay error failure',
                createdAt: new \DateTimeImmutable('2026-05-25 16:03:00'),
            ),
        );
        $this->entityManager()->run();

        $recordingOutboxLogger = new RecordingOutboxLogger();
        $publishedCount = $this->relayWithLogger($recordingOutboxLogger)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:04:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'error',
            messageSubstring: 'окончательно перевёл событие в failed',
        ));
    }

    public function testRelayTruncatesLongPublishError(): void
    {
        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new ThrowingQueueConnectionProvider(\str_repeat('x', 3000)),
        );

        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'long publish failure',
                createdAt: new \DateTimeImmutable('2026-05-25 16:09:00'),
            ),
        );
        $this->entityManager()->run();

        $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:10:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Pending, $storedOutboxEvent->status);
        self::assertLessThanOrEqual(2000, \mb_strlen($storedOutboxEvent->lastError->value() ?? ''));
    }

    public function testRelayMarksFailedOnLastAllowedPublishAttempt(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(2));
        $this->getContainer()->removeBinding(QueueConnectionProviderInterface::class);
        $this->getContainer()->bindSingleton(
            QueueConnectionProviderInterface::class,
            new ThrowingQueueConnectionProvider(),
        );

        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(
                text: 'last publish attempt',
                createdAt: new \DateTimeImmutable('2026-05-25 16:11:00'),
            ),
        );
        $this->entityManager()->run();

        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
        $storedOutboxEvent->recordPublishFailure(
            lastError: OutboxLastError::fromString('Предыдущая ошибка публикации.'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(2),
            availableAt: new \DateTimeImmutable('2099-05-25 16:12:00'),
            now: new \DateTimeImmutable('2099-05-25 16:11:30'),
        );
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:12:00'),
        );
        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
        self::assertSame(2, $storedOutboxEvent->attempts->value());
        self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }

    public function testRelayMarksNonOutboxMessageTypeAsFailed(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(1));
        $now = new \DateTimeImmutable('2099-05-25 16:19:00');
        $outboxEventId = OutboxEventId::generate();

        $this->database()
            ->insert('outbox_events')
            ->values([
                'id' => $outboxEventId->value(),
                'type' => \stdClass::class,
                'payload' => '{}',
                'status' => OutboxEventStatus::Pending->value,
                'attempts' => 0,
                'available_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'queued_at' => null,
                'handled_at' => null,
                'failed_at' => null,
                'last_error' => null,
                'created_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'updated_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->run();

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($this->outboxLastErrorIsFilledInDatabase($outboxEventId));
    }

    public function testRelayMarksUnregisteredJobAsFailed(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(1));
        $outboxEventId = $this->addOutboxMessage(new UnregisteredOutboxRelayMessage(reason: 'missing job'));
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:18:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($this->outboxLastErrorIsFilledInDatabase($outboxEventId));
    }

    public function testRelayReturnsZeroWhenNoPendingEvents(): void
    {
        $recordingOutboxLogger = new RecordingOutboxLogger();
        $publishedCount = $this->relayWithLogger($recordingOutboxLogger)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:04:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'debug',
            messageSubstring: 'не нашёл pending-события',
        ));
    }

    public function testRelayReclaimsStuckPublishingEventAndMarksFailedWhenAttemptsExhausted(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(1));

        $stuckSince = new \DateTimeImmutable('2026-05-25 16:00:00');
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(text: 'stuck publishing', createdAt: $stuckSince),
        );
        $this->entityManager()->run();

        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
        // Истёкшая claim-аренда: событие зависло в publishing, available_at в прошлом.
        $storedOutboxEvent->markPublishing(availableAt: $stuckSince, now: $stuckSince);
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        $recordingOutboxLogger = new RecordingOutboxLogger();
        $publishedCount = $this->relayWithLogger($recordingOutboxLogger)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2026-05-25 16:05:00'),
        );

        self::assertSame(0, $publishedCount);
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($outboxEventId));
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'error',
            messageSubstring: 'перевёл застрявшее в publishing событие в failed',
        ));
    }

    public function testRelayReclaimsStuckPublishingEventAndRetriesWhenAttemptsRemain(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, $this->outboxConfigWithMaxAttempts(3));

        $stuckSince = new \DateTimeImmutable('2026-05-25 16:00:00');
        $outboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogRequestedEvent(text: 'stuck publishing retry', createdAt: $stuckSince),
        );
        $this->entityManager()->run();

        $storedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
        $storedOutboxEvent->markPublishing(availableAt: $stuckSince, now: $stuckSince);
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        $publishedCount = $this->getContainer()->make(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2026-05-25 16:05:00'),
        );

        $reclaimedOutboxEvent = $this->storedOutboxEventRepository()->findById($outboxEventId);

        self::assertSame(1, $publishedCount);
        self::assertNotNull($reclaimedOutboxEvent);
        // Повторный захват исчерпал не все попытки: счётчик инкрементирован, событие опубликовано.
        self::assertSame(1, $reclaimedOutboxEvent->attempts->value());
        self::assertNotSame(OutboxEventStatus::Failed, $reclaimedOutboxEvent->status);
    }

    private function relayWithLogger(RecordingOutboxLogger $recordingOutboxLogger): OutboxRelay
    {
        return new OutboxRelay(
            storedOutboxEventRepository: $this->storedOutboxEventRepository(),
            outboxQueuePublisher: $this->getContainer()->make(OutboxQueuePublisher::class),
            outboxConfig: $this->getContainer()->get(OutboxConfig::class),
            database: $this->getContainer()->get(DatabaseInterface::class),
            logger: $recordingOutboxLogger,
        );
    }
}
