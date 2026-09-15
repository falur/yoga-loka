<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use PHPUnit\Framework\TestCase;

final class OutboxQueueSerializerTest extends TestCase
{
    public function testSerializesTransportEnvelopeAndRestoresEnvelopeWithoutDomainMessage(): void
    {
        $outboxEventId = OutboxEventId::generate();
        $outboxQueueEnvelope = new OutboxEnvelopeDto(
            outboxEventId: $outboxEventId->value(),
            outboxEventType: OutboxDebugLogRequestedEvent::class,
        );
        $queueSerializer = new OutboxQueueSerializer();

        $queuePayload = $queueSerializer->serialize($outboxQueueEnvelope);
        $transportPayload = $queueSerializer->transportPayloadFromEnvelope($outboxQueueEnvelope);
        $restoredOutboxQueueEnvelope = $queueSerializer->unserialize(
            payload: $queuePayload,
            type: OutboxEnvelopeDto::class,
        );

        self::assertSame([
            'outboxId' => $outboxEventId->value(),
            'outboxType' => OutboxDebugLogRequestedEvent::class,
        ], $transportPayload);
        self::assertInstanceOf(OutboxEnvelopeDto::class, $restoredOutboxQueueEnvelope);
        self::assertSame($outboxEventId->value(), $restoredOutboxQueueEnvelope->outboxEventId);
        self::assertSame(OutboxDebugLogRequestedEvent::class, $restoredOutboxQueueEnvelope->outboxEventType);
    }

    public function testRestoresTransportEnvelopeWithoutPayloadClass(): void
    {
        $outboxEventId = OutboxEventId::generate();
        $queueSerializer = new OutboxQueueSerializer();

        $restoredOutboxQueueEnvelope = $queueSerializer->unserialize(
            payload: $queueSerializer->serialize([
                'outboxId' => $outboxEventId->value(),
                'outboxType' => OutboxDebugLogRequestedEvent::class,
            ]),
            type: null,
        );

        self::assertInstanceOf(OutboxEnvelopeDto::class, $restoredOutboxQueueEnvelope);
        self::assertSame($outboxEventId->value(), $restoredOutboxQueueEnvelope->outboxEventId);
        self::assertSame(OutboxDebugLogRequestedEvent::class, $restoredOutboxQueueEnvelope->outboxEventType);
    }

    public function testRejectsNonArrayPayloadOnSerialize(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('payload неверного типа');

        (new OutboxQueueSerializer())->serialize('wrong');
    }

    public function testRejectsUnexpectedPayloadClassOnUnserialize(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('outbox-envelope');

        (new OutboxQueueSerializer())->unserialize(
            payload: '{}',
            type: \stdClass::class,
        );
    }

    public function testRejectsJsonArrayOnUnserialize(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('не JSON-object');

        (new OutboxQueueSerializer())->unserialize(
            payload: 'null',
            type: OutboxEnvelopeDto::class,
        );
    }

    public function testRejectsTransportPayloadWithNonStringValue(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('некорректный transport payload');

        (new OutboxQueueSerializer())->serialize([
            'outboxId' => 123,
        ]);
    }

    public function testEnvelopeRejectsEmptyRequiredTransportKey(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('outboxId');

        (new OutboxQueueSerializer())->envelopeFromTransportPayload([
            'outboxId' => '',
            'outboxType' => OutboxDebugLogRequestedEvent::class,
        ]);
    }

    public function testApplicationEnvelopeDoesNotExposeTransportPayload(): void
    {
        self::assertFalse(\is_subclass_of(OutboxEnvelopeDto::class, \JsonSerializable::class));
        self::assertFalse(\method_exists(OutboxEnvelopeDto::class, 'fromTransport'));
        self::assertFalse(\method_exists(OutboxEnvelopeDto::class, 'toTransport'));
    }
}
