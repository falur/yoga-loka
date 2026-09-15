<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Shared\Infrastructure\Spiral\Configuration\Outbox\OutboxConfig;
use CuyZ\Valinor\Mapper\MappingError;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\Exception\RetryException;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\MarkFinalThenThrowQueueStatusCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\OutboxQueueStatusInterceptorTestHelpers;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusDebugLogJobCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusTestCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RecordingOutboxLogger;
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
                    payload: new OutboxQueueEnvelope(
                        outboxEventId: $storedOutboxEvent->id,
                        outboxEventType: $storedOutboxEvent->type,
                    ),
                    outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
                    commandBus: $this->getContainer()->get(CommandBusInterface::class),
                    processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
                ),
            );
        } finally {
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
        }
    }

    public function testInterceptorMarksFailedWhenHeadersAreMissingButPayloadExists(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent(payload: '{"text":"debug"}');
        $outboxQueueEnvelope = new OutboxQueueEnvelope(
            outboxEventId: $storedOutboxEvent->id,
            outboxEventType: $storedOutboxEvent->type,
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
                    outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
                    commandBus: $this->getContainer()->get(CommandBusInterface::class),
                    processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
                ),
            );
        } finally {
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
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
                    storedOutboxEvent: $storedOutboxEvent,
                    entityManager: $this->entityManager(),
                    exception: new \RuntimeException('Job упал после перевода события в финальный статус.'),
                ),
            );
        } finally {
            // recordJobFailure увидел уже-финальное событие и не перезаписал его статус.
            self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
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
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertSame(1, $storedOutboxEvent->attempts->value());
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
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
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
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
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertLessThanOrEqual(2000, \mb_strlen($storedOutboxEvent->lastError->value() ?? ''));
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
            self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status);
            self::assertSame(1, $storedOutboxEvent->attempts->value());
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
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
            self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status);
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
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertTrue($recordingOutboxLogger->hasRecord(
                level: 'error',
                messageSubstring: 'окончательно перевёл событие в failed',
            ));
        }
    }

    private function interceptorWithLogger(RecordingOutboxLogger $recordingOutboxLogger): OutboxQueueStatusInterceptor
    {
        return new OutboxQueueStatusInterceptor(
            outboxEventRepository: $this->outboxEventRepository(),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
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
        $this->entityManager()->persist($storedOutboxEvent);
        $this->entityManager()->run();

        $this->expectException(RetryException::class);

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
                core: new QueueStatusTestCore(new RetryException(reason: 'Повторить позже.')),
            );
        } finally {
            self::assertSame(OutboxEventStatus::Failed, $storedOutboxEvent->status);
            self::assertSame(2, $storedOutboxEvent->attempts->value());
            self::assertFalse($storedOutboxEvent->failedAt->isEmpty());
            self::assertFalse($storedOutboxEvent->lastError->isEmpty());
        }
    }
}
