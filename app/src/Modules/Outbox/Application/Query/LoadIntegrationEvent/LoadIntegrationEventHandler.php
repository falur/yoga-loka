<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Query\LoadIntegrationEvent;

use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Exception\OutboxMessageLoadingException;
use App\Modules\Outbox\Application\Dto\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Repository\OutboxEventRepository;

/**
 * Чтение интеграционного события из outbox: строка должна существовать, а её тип — совпасть
 * с ожидаемым классом. Точный тип восстановленного события вызывающий получает у Result.
 */
final readonly class LoadIntegrationEventHandler
{
    public function __construct(
        private OutboxEventRepository $outboxEventRepository,
        private OutboxMessageSerializerContract $outboxMessageSerializer,
    ) {}

    public function handle(LoadIntegrationEventQuery $query): LoadIntegrationEventResult
    {
        $outboxEventId = OutboxEventId::fromString($query->outboxEventId);
        $storedOutboxEvent = $this->outboxEventRepository->findById($outboxEventId)
            ?? throw OutboxMessageLoadingException::eventNotFound(
                outboxEventId: $outboxEventId,
                expectedMessageClass: $query->expectedEventClass,
            );

        if ($storedOutboxEvent->type->value() !== $query->expectedEventClass) {
            throw OutboxMessageLoadingException::storedTypeMismatch(
                storedMessageClass: $storedOutboxEvent->type->value(),
                expectedMessageClass: $query->expectedEventClass,
            );
        }

        return new LoadIntegrationEventResult(
            integrationEvent: $this->outboxMessageSerializer->deserialize(
                serializedOutboxMessage: new SerializedOutboxMessage(
                    type: $storedOutboxEvent->type->value(),
                    payload: $storedOutboxEvent->payload->value(),
                ),
            ),
        );
    }
}
