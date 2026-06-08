<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
use PHPUnit\Framework\TestCase;

final class OutboxQueueSerializerTest extends TestCase
{
    public function testSerializesTransportEnvelopeAndRestoresEnvelopeWithoutDomainMessage(): void
    {
        $outboxEventId = OutboxEventId::generate();
        $outboxQueueEnvelope = new OutboxQueueEnvelope(
            outboxEventId: $outboxEventId,
            outboxEventType: OutboxEventType::fromString(OutboxDebugLogMessage::class),
        );
        $queueSerializer = new OutboxQueueSerializer();

        $queuePayload = $queueSerializer->serialize($outboxQueueEnvelope);
        $transportPayload = $queueSerializer->transportPayloadFromEnvelope($outboxQueueEnvelope);
        $restoredOutboxQueueEnvelope = $queueSerializer->unserialize(
            payload: $queuePayload,
            type: OutboxQueueEnvelope::class,
        );

        self::assertSame([
            'outboxId' => $outboxEventId->value(),
            'outboxType' => OutboxDebugLogMessage::class,
        ], $transportPayload);
        self::assertInstanceOf(OutboxQueueEnvelope::class, $restoredOutboxQueueEnvelope);
        self::assertTrue($outboxEventId->equals($restoredOutboxQueueEnvelope->outboxEventId));
        self::assertSame(OutboxDebugLogMessage::class, $restoredOutboxQueueEnvelope->outboxEventType->value());
    }

    public function testRestoresTransportEnvelopeWithoutPayloadClass(): void
    {
        $outboxEventId = OutboxEventId::generate();
        $queueSerializer = new OutboxQueueSerializer();

        $restoredOutboxQueueEnvelope = $queueSerializer->unserialize(
            payload: $queueSerializer->serialize([
                'outboxId' => $outboxEventId->value(),
                'outboxType' => OutboxDebugLogMessage::class,
            ]),
            type: null,
        );

        self::assertInstanceOf(OutboxQueueEnvelope::class, $restoredOutboxQueueEnvelope);
        self::assertTrue($outboxEventId->equals($restoredOutboxQueueEnvelope->outboxEventId));
        self::assertSame(OutboxDebugLogMessage::class, $restoredOutboxQueueEnvelope->outboxEventType->value());
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
            type: OutboxQueueEnvelope::class,
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
            'outboxType' => OutboxDebugLogMessage::class,
        ]);
    }

    public function testApplicationEnvelopeDoesNotExposeTransportPayload(): void
    {
        self::assertFalse(\is_subclass_of(OutboxQueueEnvelope::class, \JsonSerializable::class));
        self::assertFalse(\method_exists(OutboxQueueEnvelope::class, 'fromTransport'));
        self::assertFalse(\method_exists(OutboxQueueEnvelope::class, 'toTransport'));
    }
}
