<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Application\Message;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Application\Exception\OutboxMessageSerializationException;
use App\Modules\Outbox\Application\Dto\SerializedOutboxMessage;
use App\Modules\Outbox\Infrastructure\Serializer\ValinorOutboxMessageSerializer;
use CuyZ\Valinor\Mapper\MappingError;
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
            new OutboxDebugLogRequestedEvent(
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

        $this->expectException(MappingError::class);

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

        // Нормализатор Valinor бросает internal-исключение, поэтому проверяем стабильного родителя.
        $this->expectException(\RuntimeException::class);

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
}

final readonly class OutboxMessageSerializerTestMessage implements IntegrationEvent
{
    public function __construct(
        public string $text,
        public int $count,
    ) {}
}

final readonly class UnsupportedOutboxMessagePayload implements IntegrationEvent
{
    public function __construct(
        public \Closure $callback,
    ) {}
}
