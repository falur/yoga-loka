<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Queue;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Contract\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\Options;
use Spiral\Queue\QueueConnectionProviderInterface;

final readonly class OutboxQueuePublisher
{
    public function __construct(
        private OutboxMessageSerializerContract $outboxMessageSerializer,
        private OutboxJobRegistryContract $outboxJobRegistry,
        private QueueConnectionProviderInterface $queueConnectionProvider,
        private QueueConfig $queueConfig,
        private OutboxQueueSerializer $outboxQueueSerializer,
    ) {}

    /**
     * @return class-string
     */
    public function publish(StoredOutboxEvent $storedOutboxEvent): string
    {
        $integrationEvent = $this->outboxMessageSerializer->deserialize(
            serializedOutboxMessage: new SerializedOutboxMessage(
                type: $storedOutboxEvent->type->value(),
                payload: $storedOutboxEvent->payload->value(),
            ),
        );
        $outboxJobClass = $this->outboxJobRegistry->jobFor($integrationEvent);
        $outboxEnvelopeDto = new OutboxEnvelopeDto(
            outboxEventId: $storedOutboxEvent->id->value(),
            outboxEventType: $storedOutboxEvent->type->value(),
        );

        $this->queueConnectionProvider->getConnection()->push(
            name: $outboxJobClass,
            payload: $this->queuePayload($outboxEnvelopeDto),
            options: (new Options())
                ->withHeader(
                    name: OutboxQueueHeaders::OUTBOX_ID,
                    value: $this->nonEmptyHeaderValue($storedOutboxEvent->id->value()),
                )
                ->withHeader(
                    name: OutboxQueueHeaders::OUTBOX_TYPE,
                    value: $this->nonEmptyHeaderValue($storedOutboxEvent->type->value()),
                ),
        );

        return $outboxJobClass;
    }

    public function usesSyncConnection(): bool
    {
        $connectionName = $this->queueConfig->getDefaultDriver();
        $connectionAlias = $this->queueConfig->getAliases()[$connectionName] ?? $connectionName;

        if (!\is_string($connectionAlias)) {
            throw new \UnexpectedValueException('Алиас queue-подключения должен быть строкой.');
        }

        $connectionConfig = $this->queueConfig->getConnection($connectionAlias);
        $connectionDriver = $connectionConfig['driver'] ?? null;

        return $connectionDriver === 'sync' || $connectionDriver === SyncDriver::class;
    }

    /**
     * @return array<string, string|OutboxEnvelopeDto>
     */
    private function queuePayload(OutboxEnvelopeDto $outboxEnvelopeDto): array
    {
        if ($this->usesSyncConnection()) {
            return [
                'payload' => $outboxEnvelopeDto,
            ];
        }

        return $this->outboxQueueSerializer->transportPayloadFromEnvelope($outboxEnvelopeDto);
    }

    /**
     * @return non-empty-string
     */
    private function nonEmptyHeaderValue(string $value): string
    {
        if ($value === '') {
            throw new \UnexpectedValueException('Заголовок outbox-очереди не может быть пустым.');
        }

        return $value;
    }
}
