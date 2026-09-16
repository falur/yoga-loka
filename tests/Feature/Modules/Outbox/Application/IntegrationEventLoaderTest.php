<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Application;

use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Exception\OutboxMessageLoadingException;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Application\Contract\SerializedOutboxMessage;
use App\Modules\Outbox\Application\Query\LoadIntegrationEvent\LoadIntegrationEventHandler;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventLoaderProvider;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use CuyZ\Valinor\Mapper\MappingError;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;

final class IntegrationEventLoaderTest extends DatabaseTestCase
{
    public function testLoadsStoredIntegrationEvent(): void
    {
        $outboxEventId = $this->addIntegrationEvent(new OutboxDebugLogRequestedEvent(
            text: 'loader check',
            createdAt: new \DateTimeImmutable('2026-05-25T16:06:00+00:00'),
        ));

        $integrationEvent = $this->integrationEventLoader()->load(
            outboxEventId: $outboxEventId,
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );

        self::assertInstanceOf(OutboxDebugLogRequestedEvent::class, $integrationEvent);
        self::assertSame('loader check', $integrationEvent->text);
        self::assertEquals(new \DateTimeImmutable('2026-05-25T16:06:00+00:00'), $integrationEvent->createdAt);
    }

    public function testFailsWhenStoredEventDoesNotExist(): void
    {
        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('не найдено');

        $this->integrationEventLoader()->load(
            outboxEventId: OutboxEventId::generate()->value(),
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );
    }

    public function testFailsWhenStoredTypeDoesNotMatchExpectedType(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: IntegrationEventLoaderTestEvent::class,
            payload: '{"text":"other"}',
        );

        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('ожидался');

        $this->integrationEventLoader()->load(
            outboxEventId: $outboxEventId,
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );
    }

    public function testFailsWhenPayloadCannotBeRestored(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: OutboxDebugLogRequestedEvent::class,
            payload: '{"text":"missing date"}',
        );

        $this->expectException(MappingError::class);

        $this->integrationEventLoader()->load(
            outboxEventId: $outboxEventId,
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );
    }

    public function testFailsWhenSerializerRestoresDifferentEventType(): void
    {
        $outboxEventId = $this->persistOutboxEvent(
            type: OutboxDebugLogRequestedEvent::class,
            payload: '{"text":"debug","createdAt":"2026-05-25T16:06:00+00:00"}',
        );
        $integrationEventLoader = new IntegrationEventLoaderProvider(
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            loadIntegrationEventHandler: new LoadIntegrationEventHandler(
                storedOutboxEventRepository: $this->getContainer()->get(StoredOutboxEventRepository::class),
                outboxMessageSerializer: new DifferentIntegrationEventSerializer(),
            ),
        );

        $this->expectException(OutboxMessageLoadingException::class);
        $this->expectExceptionMessage('восстановлено как');

        $integrationEventLoader->load(
            outboxEventId: $outboxEventId,
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );
    }

    private function integrationEventLoader(): IntegrationEventLoaderContract
    {
        return $this->getContainer()->get(IntegrationEventLoaderContract::class);
    }

    private function addIntegrationEvent(IntegrationEvent $integrationEvent): string
    {
        $outboxEventId = $this->getContainer()->get(IntegrationEventStoreContract::class)->add($integrationEvent);
        $this->entityManager()->run();

        return $outboxEventId;
    }

    /**
     * @param class-string<IntegrationEvent> $type
     */
    private function persistOutboxEvent(string $type, string $payload): string
    {
        $now = new \DateTimeImmutable('2026-05-25T16:06:00+00:00');
        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString($type),
            payload: OutboxEventPayload::fromJson($payload),
            availableAt: $now,
            now: $now,
        );
        // StoredOutboxEvent — чистая доменная сущность без Cycle-разметки, поэтому в отличие
        // от прежнего прямого $entityManager->persist() сохраняется через доменный save().
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        return $storedOutboxEvent->id->value();
    }

    private function storedOutboxEventRepository(): StoredOutboxEventRepository
    {
        return $this->getContainer()->get(StoredOutboxEventRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}

final readonly class IntegrationEventLoaderTestEvent implements IntegrationEvent
{
    public function __construct(
        public string $text,
    ) {}
}

final readonly class DifferentIntegrationEventSerializer implements OutboxMessageSerializerContract
{
    #[\Override]
    public function serialize(IntegrationEvent $integrationEvent): SerializedOutboxMessage
    {
        return new SerializedOutboxMessage(
            type: $integrationEvent::class,
            payload: '{}',
        );
    }

    #[\Override]
    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): IntegrationEvent
    {
        return new IntegrationEventLoaderTestEvent('different');
    }
}
