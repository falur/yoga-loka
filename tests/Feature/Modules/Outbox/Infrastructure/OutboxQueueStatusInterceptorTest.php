<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Infrastructure\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use Cycle\ORM\EntityManagerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\OutboxQueueStatusInterceptorTestHelpers;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusDebugLogJobCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusTestCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RecordingOutboxLogger;
use Tests\TestCase;
use Tools\Cqrs\CommandBusInterface;

final class OutboxQueueStatusInterceptorTest extends TestCase
{
    use CleansOutboxEvents;
    use OutboxQueueStatusInterceptorTestHelpers;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testInterceptorSkipsJobWithoutOutboxHeader(): void
    {
        $core = new QueueStatusTestCore();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => []],
            core: $core,
        );

        self::assertTrue($core->called);
    }

    public function testInterceptorSkipsJobWhenPayloadIsNotArrayOrEnvelope(): void
    {
        $core = new QueueStatusTestCore();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['payload' => 'wrong'],
            core: $core,
        );

        self::assertTrue($core->called);
    }

    public function testInterceptorSkipsJobWhenPayloadMissesOutboxFields(): void
    {
        $core = new QueueStatusTestCore();
        $recordingOutboxLogger = new RecordingOutboxLogger();

        $this->interceptorWithLogger($recordingOutboxLogger)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['payload' => ['outboxId' => OutboxEventId::generate()->value()]],
            core: $core,
        );

        self::assertTrue($core->called);
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'warning',
            messageSubstring: 'без обоих outbox-ключей',
        ));
    }

    public function testInterceptorSkipsJobWhenPayloadFieldsAreNotStrings(): void
    {
        $core = new QueueStatusTestCore();
        $recordingOutboxLogger = new RecordingOutboxLogger();

        $this->interceptorWithLogger($recordingOutboxLogger)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: [
                'payload' => [
                    'outboxId' => 123,
                    'outboxType' => OutboxDebugLogJob::class,
                ],
            ],
            core: $core,
        );

        self::assertTrue($core->called);
        self::assertTrue($recordingOutboxLogger->hasRecord(
            level: 'warning',
            messageSubstring: 'с нестроковыми outbox-ключами',
        ));
    }

    public function testInterceptorDoesNotRunOutboxJobWithoutStoredEvent(): void
    {
        $core = new QueueStatusTestCore();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor(OutboxEventId::generate())],
            core: $core,
        );

        self::assertFalse($core->called);
    }

    public function testInterceptorSkipsAlreadyHandledEvent(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $storedOutboxEvent->markHandled(new \DateTimeImmutable('2026-05-25 16:05:00'));
        $this->entityManager()->persist($storedOutboxEvent);
        $this->entityManager()->run();
        $core = new QueueStatusTestCore();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: $core,
        );

        self::assertFalse($core->called);
    }

    public function testInterceptorMarksHandledAfterSuccessfulJob(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: new QueueStatusTestCore(),
        );

        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorDoesNotOverwriteFinalStatusChangedDuringJob(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $handledAt = new \DateTimeImmutable('2026-05-25 16:07:00');

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: new QueueStatusTestCore(afterCall: function () use ($storedOutboxEvent, $handledAt): void {
                $storedOutboxEvent->markHandled($handledAt);
                $this->entityManager()->persist($storedOutboxEvent);
                $this->entityManager()->run();
            }),
        );

        self::assertSame(OutboxEventStatus::Handled, $this->outboxEventRepository()->findFreshStatusById($storedOutboxEvent->id));
        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertSame($handledAt, $storedOutboxEvent->handledAt->value());
    }

    public function testInterceptorHandlesSerializedTransportEnvelope(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $queuePayload = (new OutboxQueueSerializer())->serialize(
            new OutboxQueueEnvelope(
                outboxEventId: $storedOutboxEvent->id,
                outboxEventType: $storedOutboxEvent->type,
            ),
        );
        $restoredPayload = (new OutboxQueueSerializer())->unserialize(
            payload: $queuePayload,
            type: OutboxQueueEnvelope::class,
        );

        if (!$restoredPayload instanceof OutboxQueueEnvelope) {
            throw new \UnexpectedValueException('Тестовый serializer вернул payload неверного типа.');
        }

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: new QueueStatusDebugLogJobCore(
                outboxDebugLogJob: $this->getContainer()->get(OutboxDebugLogJob::class),
                payload: $restoredPayload,
                outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
                commandBus: $this->getContainer()->get(CommandBusInterface::class),
                processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
            ),
        );

        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorUsesPayloadWhenHeadersAreMissing(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $outboxQueueEnvelope = new OutboxQueueEnvelope(
            outboxEventId: $storedOutboxEvent->id,
            outboxEventType: $storedOutboxEvent->type,
        );

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

        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorUsesTransportPayloadArrayWhenHeadersAreMissing(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: [
                'payload' => [
                    'outboxId' => $storedOutboxEvent->id->value(),
                    'outboxType' => $storedOutboxEvent->type->value(),
                ],
            ],
            core: new QueueStatusTestCore(),
        );

        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertFalse($storedOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorDoesNotRunJobWhenHeadersAndPayloadHaveDifferentOutboxIds(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $payloadOutboxEvent = $this->persistQueuedEvent();
        $core = new QueueStatusTestCore();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Outbox interceptor получил разные outbox-данные в headers и payload.');

        try {
            $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
                controller: OutboxDebugLogJob::class,
                action: 'handle',
                parameters: [
                    'headers' => $this->headersFor($storedOutboxEvent->id),
                    'payload' => new OutboxQueueEnvelope(
                        outboxEventId: $payloadOutboxEvent->id,
                        outboxEventType: $payloadOutboxEvent->type,
                    ),
                ],
                core: $core,
            );
        } finally {
            self::assertFalse($core->called);
            self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status);
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
}
