<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\StoreIntegrationEvent;

use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

/**
 * Запись интеграционного события в outbox. Сценарий вызывается из транзакции источника, поэтому
 * его #[Transactional] вложенный и даёт SAVEPOINT, а flush остаётся за источником: свой
 * EntityManager::run() здесь не вызывается, иначе событие ушло бы в базу раньше бизнес-изменения.
 */
final readonly class StoreIntegrationEventHandler
{
    public function __construct(
        private OutboxMessageSerializerContract $outboxMessageSerializer,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    public function handle(StoreIntegrationEventCommand $command): StoreIntegrationEventResult
    {
        $serializedOutboxMessage = $this->outboxMessageSerializer->serialize($command->integrationEvent);
        $storedOutboxEvent = StoredOutboxEvent::create(
            type: OutboxEventType::fromString($serializedOutboxMessage->type),
            payload: OutboxEventPayload::fromJson($serializedOutboxMessage->payload),
        );

        $this->entityManager->persist($storedOutboxEvent);

        return new StoreIntegrationEventResult(outboxEventId: $storedOutboxEvent->id->value());
    }
}
