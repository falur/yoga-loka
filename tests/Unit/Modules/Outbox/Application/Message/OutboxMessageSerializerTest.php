<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Application\Message;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxMessageSerializationException;
use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Outbox\Infrastructure\Message\ValinorOutboxMessageSerializer;
use PHPUnit\Framework\TestCase;

final class OutboxMessageSerializerTest extends TestCase
{
    public function testSerializesAndRestoresTypedMessage(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $serializedOutboxMessage = $serializer->serialize(
            new OutboxMessageSerializerTestMessage(
                text: 'Тестовое сообщение',
                count: 3,
            ),
        );
        $restoredOutboxMessage = $serializer->deserialize(
            serializedOutboxMessage: $serializedOutboxMessage,
        );

        self::assertSame(OutboxMessageSerializerTestMessage::class, $serializedOutboxMessage->type);
        self::assertJson($serializedOutboxMessage->payload);
        self::assertInstanceOf(OutboxMessageSerializerTestMessage::class, $restoredOutboxMessage);
        self::assertSame('Тестовое сообщение', $restoredOutboxMessage->text);
        self::assertSame(3, $restoredOutboxMessage->count);
    }

    public function testDebugLogMessageSerializesToCamelCasePayload(): void
    {
        $serializedOutboxMessage = (new ValinorOutboxMessageSerializer())->serialize(
            new OutboxDebugLogMessage(
                text: 'debug',
                createdAt: new \DateTimeImmutable('2026-05-25T16:06:00+00:00'),
            ),
        );

        self::assertJsonStringEqualsJsonString(
            '{"text":"debug","createdAt":"2026-05-25T16:06:00.000000+00:00"}',
            $serializedOutboxMessage->payload,
        );
    }

    public function testDeserializerFailsOnIncompatiblePayload(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $this->expectException(OutboxMessageSerializationException::class);

        $serializer->deserialize(
            serializedOutboxMessage: new SerializedOutboxMessage(
                type: OutboxMessageSerializerTestMessage::class,
                payload: '{"text":"ok","count":"not-int"}',
            ),
        );
    }

    public function testSerializerFailsOnUnsupportedMessagePayload(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $this->expectException(OutboxMessageSerializationException::class);

        $serializer->serialize(new UnsupportedOutboxMessagePayload(static fn(): string => 'unsupported'));
    }

    public function testDeserializerRejectsClassWithoutOutboxMessageContract(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $this->expectException(OutboxMessageSerializationException::class);

        $serializer->deserialize(
            serializedOutboxMessage: new SerializedOutboxMessage(
                type: \stdClass::class,
                payload: '{}',
            ),
        );
    }

    public function testStoredOutboxEventIdRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        StoredOutboxEventId::fromString('');
    }
}

final readonly class OutboxMessageSerializerTestMessage implements OutboxMessage
{
    public function __construct(
        public string $text,
        public int $count,
    ) {}
}

final readonly class UnsupportedOutboxMessagePayload implements OutboxMessage
{
    public function __construct(
        public \Closure $callback,
    ) {}
}
