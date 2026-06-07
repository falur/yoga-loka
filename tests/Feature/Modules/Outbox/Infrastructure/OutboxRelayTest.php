<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\OutboxQueuePublisher;
use App\Modules\Outbox\Infrastructure\OutboxRelay;
use App\Modules\Outbox\Repository\InvalidOutboxPendingRow;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Modules\Outbox\Repository\OutboxPendingRow;
use App\Modules\Outbox\Repository\ValidOutboxPendingRow;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use App\Shared\Infrastructure\Database\DatabaseDateTimeFormat;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\Exception\TypecastException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\FailingOutboxRelayJob;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\FailingOutboxRelayMessage;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\OutboxRelayTestHelpers;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RecordingOutboxLogger;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RetryingOutboxRelayJob;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RetryingOutboxRelayMessage;
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
            new OutboxDebugLogMessage(
                text: 'sync relay check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:07:00'),
            ),
        );
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:08:00'),
        );
        $storedOutboxEvent = $this->outboxEventRepository()->findById($outboxEventId);

        self::assertSame(1, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->queuedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testRelayDoesNotOverwriteFailedStatusWhenSyncJobFails(): void
    {
        $this->registerOutboxJob(
            outboxMessageClass: FailingOutboxRelayMessage::class,
            outboxJobClass: FailingOutboxRelayJob::class,
        );
        $outboxEventId = $this->addOutboxMessage(new FailingOutboxRelayMessage(reason: 'sync failure'));
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:15:00'),
        );
        $storedOutboxEvent = $this->outboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }

    public function testRelayMarksInvalidPendingRowAsFailedAndKeepsProcessingValidEvents(): void
    {
        $now = new \DateTimeImmutable('2099-06-05 17:00:00');
        $goodOutboxEventId = $this->addOutboxMessage(
            new OutboxDebugLogMessage(
                text: 'good event for invalid-row recovery',
                createdAt: new \DateTimeImmutable('2026-05-25 16:07:00'),
            ),
        );
        $this->entityManager()->run();

        $badOutboxEventId = $this->insertCorruptPendingRow(attempts: -1, now: $now);

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );

        self::assertSame(1, $publishedCount);
        self::assertSame(OutboxEventStatus::Handled->value, $this->outboxStatusInDatabase($goodOutboxEventId));
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($badOutboxEventId));
        self::assertTrue($this->outboxLastErrorIsFilledInDatabase($badOutboxEventId));
    }

    public function testRelayLogsRecoveryEntryAndInvalidRowAsWarning(): void
    {
        $now = new \DateTimeImmutable('2099-06-05 18:00:00');
        $this->insertCorruptPendingRow(attempts: -1, now: $now);

        $recordingOutboxLogger = new RecordingOutboxLogger();
        $outboxRelay = new OutboxRelay(
            outboxEventRepository: $this->getContainer()->get(OutboxEventRepository::class),
            outboxQueuePublisher: $this->getContainer()->get(OutboxQueuePublisher::class),
            outboxConfig: $this->getContainer()->get(OutboxConfig::class),
            database: $this->getContainer()->get(DatabaseInterface::class),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            logger: $recordingOutboxLogger,
        );

        $publishedCount = $outboxRelay->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );

        self::assertSame(0, $publishedCount);
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'warning',
            messageSubstring: 'вошёл в восстановление',
        ));
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'warning',
            messageSubstring: 'пометил повреждённую строку failed',
        ));
    }

    public function testRelaySecondRunDoesNotRecaptureFailedInvalidRow(): void
    {
        $now = new \DateTimeImmutable('2099-06-05 17:30:00');
        $badOutboxEventId = $this->insertCorruptPendingRow(attempts: -1, now: $now);

        $firstRunPublishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );
        $secondRunPublishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now->modify('+1 minute'),
        );

        self::assertSame(0, $firstRunPublishedCount);
        self::assertSame(0, $secondRunPublishedCount);
        self::assertSame(OutboxEventStatus::Failed->value, $this->outboxStatusInDatabase($badOutboxEventId));
        self::assertTrue($this->outboxEventRepository()->pendingForRelayRows(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now->modify('+1 minute'),
        )->isEmpty());
    }

    public function testRecoveryRethrowsOriginalErrorWhenNoInvalidRowIsMarkedFailed(): void
    {
        $now = new \DateTimeImmutable('2099-06-05 20:00:00');

        // Реальная битая строка: ORM-гидрация на ней упадёт TypecastException, что и запускает recovery.
        $badOutboxEventId = $this->insertCorruptPendingRow(attempts: -1, now: $now);

        // Гонка статуса: как только relay вошёл в восстановление, битую строку увели из pending в
        // другой статус. Тогда recovery не помечает ни одной строки failed (failedRowsCount = 0) и
        // обязан пробросить исходную ошибку наверх, а не зациклиться на повторной падающей выборке.
        // Это и есть страховка из фикса: распознавание само по себе больше не считается прогрессом —
        // прогресс — только реально затронутая CAS строка.
        $recordingOutboxLogger = new RecordingOutboxLogger();
        $statusRacingLogger = $this->loggerFlippingRowToHandledOnRecoveryEntry(
            badOutboxEventId: $badOutboxEventId,
            now: $now,
            delegateLogger: $recordingOutboxLogger,
        );

        $outboxRelay = new OutboxRelay(
            outboxEventRepository: $this->getContainer()->get(OutboxEventRepository::class),
            outboxQueuePublisher: $this->getContainer()->get(OutboxQueuePublisher::class),
            outboxConfig: $this->getContainer()->get(OutboxConfig::class),
            database: $this->getContainer()->get(DatabaseInterface::class),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            logger: $statusRacingLogger,
        );

        try {
            $outboxRelay->relay(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
                now: $now,
            );
            self::fail('Ожидался проброс исходного TypecastException, а не зацикливание на повторной выборке.');
        } catch (TypecastException) {
            self::assertTrue(true);
        }

        // Битую строку recovery не помечал failed (нулевой прогресс), а откат claim-транзакции вернул
        // подменённый статус обратно — строка снова pending. Ложного перевода в failed не произошло.
        self::assertSame(OutboxEventStatus::Pending->value, $this->outboxStatusInDatabase($badOutboxEventId));
        // Recovery вошёл в восстановление, но ни одной строки failed не пометил.
        self::assertTrue($recordingOutboxLogger->hasRecord(level: 'warning', messageSubstring: 'вошёл в восстановление'));
        self::assertFalse($recordingOutboxLogger->hasRecord(level: 'warning', messageSubstring: 'пометил повреждённую строку failed'));
    }

    public function testRelayDoesNotOverwriteQueuedStatusWhenSyncJobRetries(): void
    {
        $this->registerOutboxJob(
            outboxMessageClass: RetryingOutboxRelayMessage::class,
            outboxJobClass: RetryingOutboxRelayJob::class,
        );
        $outboxEventId = $this->addOutboxMessage(new RetryingOutboxRelayMessage(reason: 'sync retry'));
        $this->entityManager()->run();

        $publishedCount = $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-05-25 16:16:00'),
        );
        $storedOutboxEvent = $this->outboxEventRepository()->findById($outboxEventId);

        self::assertSame(0, $publishedCount);
        self::assertNotNull($storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status);
        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertTrue($storedOutboxEvent->failedAt->isEmpty());
        self::assertFalse($storedOutboxEvent->lastError->isEmpty());
    }

    public function testManualParserAndOrmTypecastAgreeOnSameRows(): void
    {
        $now = new \DateTimeImmutable('2099-06-05 19:00:00');
        $this->addOutboxMessage(
            new OutboxDebugLogMessage(
                text: 'consistency valid event',
                createdAt: new \DateTimeImmutable('2026-05-25 16:07:00'),
            ),
        );
        $this->entityManager()->run();
        $this->insertCorruptPendingRow(attempts: -1, now: $now);

        // Ручной парсер видит обе строки: валидную как ValidOutboxPendingRow, битую как
        // InvalidOutboxPendingRow. Так recovery всегда учитывает повреждённую строку и не
        // расходится с ORM-typecast (failedRowsCount не останется 0, relay не зациклится).
        $pendingRows = $this->outboxEventRepository()->pendingForRelayRows(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: $now,
        );

        self::assertCount(2, $pendingRows);
        self::assertCount(1, $pendingRows->filter(static fn(OutboxPendingRow $pendingRow): bool => $pendingRow instanceof ValidOutboxPendingRow));
        self::assertCount(1, $pendingRows->filter(static fn(OutboxPendingRow $pendingRow): bool => $pendingRow instanceof InvalidOutboxPendingRow));

        // ORM-typecast отвергает тот же набор: гидрация падает TypecastException на битой
        // строке, которую ручной парсер пометил InvalidOutboxPendingRow.
        try {
            $this->outboxEventRepository()->findPendingForRelay(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
                now: $now,
            );
            self::fail('Ожидался TypecastException на повреждённой строке при ORM-гидрации.');
        } catch (TypecastException) {
            self::assertTrue(true);
        }
    }

    /**
     * Логгер, который ровно при входе relay в восстановление (до чтения сырых строк) имитирует
     * гонку статуса: переводит битую строку из pending в другой статус, поэтому её больше не
     * увидит ни выборка сырых строк, ни CAS-пометка failed. После этого recovery не помечает ни
     * одной строки (failedRowsCount = 0) и обязан пробросить исходную ошибку. Делегирует записи в
     * переданный логгер, чтобы тест мог проверить, что «пометил повреждённую строку failed» не было.
     */
    private function loggerFlippingRowToHandledOnRecoveryEntry(
        OutboxEventId $badOutboxEventId,
        \DateTimeImmutable $now,
        LoggerInterface $delegateLogger,
    ): LoggerInterface {
        $database = $this->database();

        return new class ($database, $badOutboxEventId, $now, $delegateLogger) extends AbstractLogger {
            public function __construct(
                private readonly DatabaseInterface $database,
                private readonly OutboxEventId $badOutboxEventId,
                private readonly \DateTimeImmutable $now,
                private readonly LoggerInterface $delegateLogger,
            ) {}

            #[\Override]
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->delegateLogger->log($level, $message, $context);

                if (!\str_contains((string) $message, 'вошёл в восстановление')) {
                    return;
                }

                $this->database
                    ->update('outbox_events')
                    ->values([
                        'status' => OutboxEventStatus::Handled->value,
                        'handled_at' => $this->now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                        'updated_at' => $this->now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                    ])
                    ->where('id', $this->badOutboxEventId->value())
                    ->run();
            }
        };
    }

    private function insertCorruptPendingRow(int $attempts, \DateTimeImmutable $now): OutboxEventId
    {
        $badOutboxEventId = OutboxEventId::generate();

        $this->database()
            ->insert('outbox_events')
            ->values([
                'id' => $badOutboxEventId->value(),
                'type' => OutboxEventType::fromString(OutboxDebugLogMessage::class)->value(),
                'payload' => '{"text":"bad event","createdAt":"2026-05-25T16:06:00+00:00"}',
                'status' => OutboxEventStatus::Pending->value,
                'attempts' => $attempts,
                'available_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'queued_at' => null,
                'handled_at' => null,
                'failed_at' => null,
                'last_error' => null,
                'created_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'updated_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->run();

        return $badOutboxEventId;
    }
}
