<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\OutboxMessageLoadingException;
use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Infrastructure\OutboxMessageLoader;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use Cycle\ORM\EntityManagerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;

final class OutboxMessageLoaderTest extends TestCase
{
    use CleansOutboxEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testLoadsStoredOutboxMessage(): void
    {
        $outboxEventId = $this->addOutboxMessage(new OutboxDebugLogMessage(
            text: 'loader check',
            createdAt: new \DateTimeImmutable('2026-05-25T16:06:00+00:00'),
        ));

        $outboxMessage = $this->getContainer()->get(OutboxMessageLoaderContract::class)->load(
            outboxEventId: $outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
        );

        self::assertInstanceOf(OutboxDebugLogMessage::class, $outboxMessage);
        self::assertSame('loader check', $outboxMessage->text);
        self::assertEquals(new \DateTimeImmutable('2026-05-25T16:06:00+00:00'), $outboxMessage->createdAt);
    }

    public function testFailsWhenStoredEventDoesNotExist(): void
    {
        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('не найдено');

        $this->getContainer()->get(OutboxMessageLoaderContract::class)->load(
            outboxEventId: OutboxEventId::generate(),
            expectedMessageClass: OutboxDebugLogMessage::class,
        );
    }

    public function testFailsWhenStoredTypeDoesNotMatchExpectedType(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: OutboxMessageLoaderTestMessage::class,
            payload: '{"text":"other"}',
        );

        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('ожидался');

        $this->getContainer()->get(OutboxMessageLoaderContract::class)->load(
            outboxEventId: $outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
        );
    }

    public function testFailsWhenPayloadCannotBeRestored(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: OutboxDebugLogMessage::class,
            payload: '{"text":"missing date"}',
        );

        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('Не удалось загрузить outbox-сообщение');

        $this->getContainer()->get(OutboxMessageLoaderContract::class)->load(
            outboxEventId: $outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
        );
    }

    public function testFailsWhenSerializerRestoresDifferentMessageType(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: OutboxDebugLogMessage::class,
            payload: '{"text":"debug","createdAt":"2026-05-25T16:06:00+00:00"}',
        );
        $outboxMessageLoader = new OutboxMessageLoader(
            outboxEventRepository: $this->getContainer()->get(OutboxEventRepository::class),
            outboxMessageSerializer: new DifferentOutboxMessageSerializer(),
        );

        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('восстановлено как');

        $outboxMessageLoader->load(
            outboxEventId: $outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
        );
    }

    private function addOutboxMessage(OutboxMessage $outboxMessage): OutboxEventId
    {
        $storedOutboxEventId = $this->getContainer()->get(OutboxEventStoreContract::class)->add($outboxMessage);
        $this->entityManager()->run();

        return OutboxEventId::fromString($storedOutboxEventId->value());
    }

    /**
     * @param class-string<OutboxMessage> $type
     */
    private function persistOutboxEvent(string $type, string $payload): OutboxEventId
    {
        $now = new \DateTimeImmutable('2026-05-25T16:06:00+00:00');
        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString($type),
            payload: OutboxEventPayload::fromJson($payload),
            availableAt: $now,
            now: $now,
        );
        $this->entityManager()->persist($storedOutboxEvent);
        $this->entityManager()->run();

        return $storedOutboxEvent->id;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}

final readonly class OutboxMessageLoaderTestMessage implements OutboxMessage
{
    public function __construct(
        public string $text,
    ) {}
}

final readonly class DifferentOutboxMessageSerializer implements OutboxMessageSerializerContract
{
    #[\Override]
    public function serialize(OutboxMessage $outboxMessage): SerializedOutboxMessage
    {
        return new SerializedOutboxMessage(
            type: $outboxMessage::class,
            payload: '{}',
        );
    }

    #[\Override]
    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): OutboxMessage
    {
        return new OutboxMessageLoaderTestMessage('different');
    }
}
