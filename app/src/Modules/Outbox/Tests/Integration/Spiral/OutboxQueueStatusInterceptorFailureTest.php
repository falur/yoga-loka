<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Modules\Outbox\Infrastructure\Spiral\Configuration\OutboxConfig;
use CuyZ\Valinor\Mapper\MappingError;
use Cycle\Database\DatabaseInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\Exception\RetryException;
use Tests\Support\Outbox\CleansOutboxEvents;
use App\Modules\Outbox\Tests\Integration\Spiral\DeleteEventDuringJobQueueStatusCore;
use App\Modules\Outbox\Tests\Integration\Spiral\MarkFinalThenThrowQueueStatusCore;
use App\Modules\Outbox\Tests\Integration\Spiral\OutboxQueueStatusInterceptorTestHelpers;
use App\Modules\Outbox\Tests\Integration\Spiral\QueueStatusDebugLogJobCore;
use App\Modules\Outbox\Tests\Integration\Spiral\QueueStatusTestCore;
use App\Modules\Outbox\Tests\Integration\Spiral\RecordingOutboxLogger;
use Tests\TestCase;

final class OutboxQueueStatusInterceptorFailureTest extends TestCase
{
    use CleansOutboxEvents;
    use OutboxQueueStatusInterceptorTestHelpers;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testPayloadRestoreFailureInsideJobMarksOutboxEventFailed(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent(payload: '{"text":"debug"}');

        $this->expectException(MappingError::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusDebugLogJobCore(
                    outboxDebugLogJob: $this->getContainer()->get(OutboxDebugLogJob::class),
                    payload: new OutboxEnvelopeDto(
                        outboxEventId: $storedOutboxEvent->id->value(),
                        outboxEventType: $storedOutboxEvent->type->value(),
                    ),
                    integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
                    commandBus: $this->getContainer()->get(CommandBusInterface::class),
                    processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
                ),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertFalse($reloadedStoredOutboxEvent->failedAt->isEmpty());
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorMarksFailedWhenHeadersAreMissingButPayloadExists(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent(payload: '{"text":"debug"}');
        $outboxQueueEnvelope = new OutboxEnvelopeDto(
            outboxEventId: $storedOutboxEvent->id->value(),
            outboxEventType: $storedOutboxEvent->type->value(),
        );

        $this->expectException(MappingError::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['payload' => $outboxQueueEnvelope],
                core: new QueueStatusDebugLogJobCore(
                    outboxDebugLogJob: $this->getContainer()->get(OutboxDebugLogJob::class),
                    payload: $outboxQueueEnvelope,
                    integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
                    commandBus: $this->getContainer()->get(CommandBusInterface::class),
                    processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
                ),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertFalse($reloadedStoredOutboxEvent->failedAt->isEmpty());
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorSkipsFailureWhenEventBecameFinalDuringJob(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(\RuntimeException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new MarkFinalThenThrowQueueStatusCore(
                    storedOutboxEventRepository: $this->storedOutboxEventRepository(),
                    outboxEventId: $storedOutboxEvent->id,
                    exception: new \RuntimeException('Job упал после перевода события в финальный статус.'),
                ),
            );
        } finally {
            // recordJobFailure перечитал состояние из репозитория, увидел уже-финальное событие
            // (сохранённое fixture-ом через отдельный findById(), а не общий PHP-объект) и не
            // перезаписал его статус ошибкой — проверка идёт по реальной строке БД.
            self::assertSame(OutboxEventStatus::Handled, $this->reloadStoredOutboxEvent($storedOutboxEvent->id)->status);
        }
    }

    /**
     * Зеркало успешной ветки: строка события исчезла, пока Job работал, и Job при этом упал.
     * Перечит вернёт null, поэтому ошибка записывается в снимок, прочитанный до запуска Job, —
     * interceptor не падает второй ошибкой и не теряет факт неудачи.
     */
    public function testInterceptorSurvivesEventDeletedDuringFailedJob(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(\RuntimeException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new DeleteEventDuringJobQueueStatusCore(
                    database: $this->getContainer()->get(DatabaseInterface::class),
                    outboxEventId: $storedOutboxEvent->id,
                    exception: new \RuntimeException('Job упал после исчезновения своего события.'),
                ),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorMarksFailedAndRethrowsJobError(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(\RuntimeException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new \RuntimeException('Job упал.')),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertSame(1, $reloadedStoredOutboxEvent->attempts->value());
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorMarksFailedWhenJobRegistrationIsBroken(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(\UnexpectedValueException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(
                    new \UnexpectedValueException('Outbox Job handler не зарегистрирован.'),
                ),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorTruncatesLongJobError(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(\RuntimeException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new \RuntimeException(\str_repeat('x', 3000))),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertLessThanOrEqual(2000, \mb_strlen($reloadedStoredOutboxEvent->lastError->value() ?? ''));
        }
    }

    public function testInterceptorKeepsQueuedStatusForRetryError(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->expectException(RetryException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new RetryException(reason: 'Повторить позже.')),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Queued, $reloadedStoredOutboxEvent->status);
            self::assertSame(1, $reloadedStoredOutboxEvent->attempts->value());
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorLogsRetryThatStaysQueuedOnDebug(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $recordingOutboxLogger = new RecordingOutboxLogger();

        $this->expectException(RetryException::class);

        try {
            $this->interceptorWithLogger($recordingOutboxLogger)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new RetryException(reason: 'Повторить позже.')),
            );
        } finally {
            self::assertSame(
                OutboxEventStatus::Queued,
                $this->reloadStoredOutboxEvent($storedOutboxEvent->id)->status,
            );
            self::assertTrue($recordingOutboxLogger->hasRecord(
                level: 'debug',
                messageSubstring: 'оставил событие на повтор',
            ));
            self::assertFalse($recordingOutboxLogger->hasRecord(
                level: 'error',
                messageSubstring: 'окончательно перевёл событие в failed',
            ));
        }
    }

    public function testInterceptorLogsFinalFailureOnError(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $recordingOutboxLogger = new RecordingOutboxLogger();

        $this->expectException(\RuntimeException::class);

        try {
            $this->interceptorWithLogger($recordingOutboxLogger)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new \RuntimeException('Job окончательно упал.')),
            );
        } finally {
            self::assertSame(
                OutboxEventStatus::Failed,
                $this->reloadStoredOutboxEvent($storedOutboxEvent->id)->status,
            );
            self::assertTrue($recordingOutboxLogger->hasRecord(
                level: 'error',
                messageSubstring: 'окончательно перевёл событие в failed',
            ));
        }
    }

    private function interceptorWithLogger(RecordingOutboxLogger $recordingOutboxLogger): OutboxQueueStatusInterceptor
    {
        return new OutboxQueueStatusInterceptor(
            storedOutboxEventRepository: $this->storedOutboxEventRepository(),
            outboxConfig: $this->getContainer()->get(OutboxConfig::class),
            logger: $recordingOutboxLogger,
            outboxQueueSerializer: $this->getContainer()->get(OutboxQueueSerializer::class),
        );
    }

    public function testInterceptorMarksFailedOnLastAllowedRetryAttempt(): void
    {
        $this->getContainer()->removeBinding(OutboxConfig::class);
        $this->getContainer()->bindSingleton(OutboxConfig::class, new OutboxConfig(
            maxAttempts: 2,
            maxConsecutiveRelayFailures: 10,
            baseRelayRetryDelaySeconds: 1,
            maxRelayRetryDelaySeconds: 30,
            claimTimeoutSeconds: 60,
            publishRetryDelaySeconds: 60,
        ));
        $storedOutboxEvent = $this->persistQueuedEvent();
        $storedOutboxEvent->recordJobRetry(
            lastError: OutboxLastError::fromString('Предыдущая ошибка Job.'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(2),
            now: new \DateTimeImmutable('2026-05-25 16:05:30'),
        );
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        $this->expectException(RetryException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new RetryException(reason: 'Повторить позже.')),
            );
        } finally {
            $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
            self::assertSame(OutboxEventStatus::Failed, $reloadedStoredOutboxEvent->status);
            self::assertSame(2, $reloadedStoredOutboxEvent->attempts->value());
            self::assertFalse($reloadedStoredOutboxEvent->failedAt->isEmpty());
            self::assertFalse($reloadedStoredOutboxEvent->lastError->isEmpty());
        }
    }
}
