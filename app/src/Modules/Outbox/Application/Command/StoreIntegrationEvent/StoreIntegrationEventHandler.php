<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\StoreIntegrationEvent;

use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

/**
 * Запись интеграционного события в outbox. Сценарий вызывается из транзакции источника, поэтому
 * его #[Transactional] вложенный и даёт SAVEPOINT, а событие только ставится в текущую запись
 * методом add(): в базу его уносит прогон источника, иначе оно ушло бы туда раньше
 * бизнес-изменения.
 */
final readonly class StoreIntegrationEventHandler
{
    public function __construct(
        private OutboxMessageSerializerContract $outboxMessageSerializer,
        private StoredOutboxEventRepository $storedOutboxEventRepository,
    ) {}

    #[Transactional]
    public function handle(StoreIntegrationEventCommand $command): StoreIntegrationEventResult
    {
        $serializedOutboxMessage = $this->outboxMessageSerializer->serialize($command->integrationEvent);
        $storedOutboxEvent = StoredOutboxEvent::create(
            type: OutboxEventType::fromString($serializedOutboxMessage->type),
            payload: OutboxEventPayload::fromJson($serializedOutboxMessage->payload),
        );

        $this->storedOutboxEventRepository->add($storedOutboxEvent);

        return new StoreIntegrationEventResult(outboxEventId: $storedOutboxEvent->id->value());
    }
}
