<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Modules\Outbox\Infrastructure\Spiral\Configuration\OutboxConfig;
use Cycle\Database\DatabaseInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\DeleteEventDuringJobQueueStatusCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\OutboxQueueStatusInterceptorTestHelpers;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusDebugLogJobCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\QueueStatusTestCore;
use Tests\Feature\Modules\Outbox\Infrastructure\Fixture\RecordingOutboxLogger;
use Tests\TestCase;

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
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);
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

        $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
        self::assertSame(OutboxEventStatus::Handled, $reloadedStoredOutboxEvent->status);
        self::assertFalse($reloadedStoredOutboxEvent->handledAt->isEmpty());
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
                $this->storedOutboxEventRepository()->save($storedOutboxEvent);
            }),
        );

        self::assertSame(OutboxEventStatus::Handled, $this->storedOutboxEventRepository()->findById($storedOutboxEvent->id)?->status);
        self::assertSame(OutboxEventStatus::Handled, $storedOutboxEvent->status);
        self::assertSame($handledAt, $storedOutboxEvent->handledAt->value());
    }

    /**
     * Строка события может исчезнуть, пока Job работает (параллельная чистка). Перечит вернёт
     * null, и interceptor опирается на снимок, прочитанный до запуска Job: он не падает и
     * доводит отработавшее событие до handled, не теряя факт успешной обработки.
     */
    public function testInterceptorSurvivesEventDeletedDuringSuccessfulJob(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: new DeleteEventDuringJobQueueStatusCore(
                database: $this->getContainer()->get(DatabaseInterface::class),
                outboxEventId: $storedOutboxEvent->id,
            ),
        );

        $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
        self::assertSame(OutboxEventStatus::Handled, $reloadedStoredOutboxEvent->status);
        self::assertFalse($reloadedStoredOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorHandlesSerializedTransportEnvelope(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $queuePayload = (new OutboxQueueSerializer())->serialize(
            new OutboxEnvelopeDto(
                outboxEventId: $storedOutboxEvent->id->value(),
                outboxEventType: $storedOutboxEvent->type->value(),
            ),
        );
        $restoredPayload = (new OutboxQueueSerializer())->unserialize(
            payload: $queuePayload,
            type: OutboxEnvelopeDto::class,
        );

        if (!$restoredPayload instanceof OutboxEnvelopeDto) {
            throw new \UnexpectedValueException('Тестовый serializer вернул payload неверного типа.');
        }

        $this->getContainer()->get(OutboxQueueStatusInterceptor::class)->process(
            controller: OutboxDebugLogJob::class,
            action: 'handle',
            parameters: ['headers' => $this->headersFor($storedOutboxEvent->id)],
            core: new QueueStatusDebugLogJobCore(
                outboxDebugLogJob: $this->getContainer()->get(OutboxDebugLogJob::class),
                payload: $restoredPayload,
                integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
                commandBus: $this->getContainer()->get(CommandBusInterface::class),
                processOutboxDebugLogMessageHandler: $this->getContainer()->get(ProcessOutboxDebugLogMessageHandler::class),
            ),
        );

        $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
        self::assertSame(OutboxEventStatus::Handled, $reloadedStoredOutboxEvent->status);
        self::assertFalse($reloadedStoredOutboxEvent->handledAt->isEmpty());
    }

    public function testInterceptorUsesPayloadWhenHeadersAreMissing(): void
    {
        $storedOutboxEvent = $this->persistQueuedEvent();
        $outboxQueueEnvelope = new OutboxEnvelopeDto(
            outboxEventId: $storedOutboxEvent->id->value(),
            outboxEventType: $storedOutboxEvent->type->value(),
        );

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

        $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
        self::assertSame(OutboxEventStatus::Handled, $reloadedStoredOutboxEvent->status);
        self::assertFalse($reloadedStoredOutboxEvent->handledAt->isEmpty());
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

        $reloadedStoredOutboxEvent = $this->reloadStoredOutboxEvent($storedOutboxEvent->id);
        self::assertSame(OutboxEventStatus::Handled, $reloadedStoredOutboxEvent->status);
        self::assertFalse($reloadedStoredOutboxEvent->handledAt->isEmpty());
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
                    'payload' => new OutboxEnvelopeDto(
                        outboxEventId: $payloadOutboxEvent->id->value(),
                        outboxEventType: $payloadOutboxEvent->type->value(),
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
            storedOutboxEventRepository: $this->storedOutboxEventRepository(),
            outboxConfig: $this->getContainer()->get(OutboxConfig::class),
            logger: $recordingOutboxLogger,
            outboxQueueSerializer: $this->getContainer()->get(OutboxQueueSerializer::class),
        );
    }
}
