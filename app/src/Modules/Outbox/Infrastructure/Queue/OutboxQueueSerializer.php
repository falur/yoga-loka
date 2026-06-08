<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Queue;

use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use Spiral\Serializer\SerializerInterface;

final readonly class OutboxQueueSerializer implements SerializerInterface
{
    #[\Override]
    public function serialize(mixed $payload): string
    {
        if ($payload instanceof OutboxQueueEnvelope) {
            return $this->encode($this->transportPayloadFromEnvelope($payload));
        }

        if (!\is_array($payload)) {
            throw new \UnexpectedValueException('Outbox serializer получил payload неверного типа.');
        }

        return $this->encode($this->normalizeTransportPayload($payload));
    }

    #[\Override]
    public function unserialize(string|\Stringable $payload, string|object|null $type = null): mixed
    {
        if ($type !== null && $type !== OutboxQueueEnvelope::class) {
            throw new \UnexpectedValueException('Outbox serializer не получил класс outbox-envelope.');
        }

        $transportPayload = $this->decode((string) $payload);

        return $this->envelopeFromTransportPayload($transportPayload);
    }

    /**
     * @return array<string, string>
     */
    public function transportPayloadFromEnvelope(OutboxQueueEnvelope $outboxQueueEnvelope): array
    {
        return [
            OutboxQueueHeaders::OUTBOX_ID => $outboxQueueEnvelope->outboxEventId->value(),
            OutboxQueueHeaders::OUTBOX_TYPE => $outboxQueueEnvelope->outboxEventType->value(),
        ];
    }

    /**
     * @param array<string, string> $transportPayload
     */
    public function envelopeFromTransportPayload(array $transportPayload): OutboxQueueEnvelope
    {
        $this->assertRequiredStringKey(transportPayload: $transportPayload, key: OutboxQueueHeaders::OUTBOX_ID);
        $this->assertRequiredStringKey(transportPayload: $transportPayload, key: OutboxQueueHeaders::OUTBOX_TYPE);

        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::fromString($transportPayload[OutboxQueueHeaders::OUTBOX_ID]),
            outboxEventType: OutboxEventType::fromString($transportPayload[OutboxQueueHeaders::OUTBOX_TYPE]),
        );
    }

    /**
     * @param array<string, string> $transportPayload
     */
    private function encode(array $transportPayload): string
    {
        return \json_encode(value: $transportPayload, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function decode(string $payload): array
    {
        $transportPayload = \json_decode(
            json: $payload,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!\is_array($transportPayload)) {
            throw new \UnexpectedValueException('Outbox serializer получил не JSON-object.');
        }

        return $this->normalizeTransportPayload($transportPayload);
    }

    /**
     * @param array<int|string, mixed> $transportPayload
     * @return array<string, string>
     */
    private function normalizeTransportPayload(array $transportPayload): array
    {
        $normalizedTransportPayload = [];

        foreach ($transportPayload as $key => $value) {
            if (!\is_string($key) || !\is_string($value)) {
                throw new \UnexpectedValueException('Outbox serializer получил некорректный transport payload.');
            }

            $normalizedTransportPayload[$key] = $value;
        }

        return $normalizedTransportPayload;
    }

    /**
     * @param array<string, string> $transportPayload
     */
    private function assertRequiredStringKey(array $transportPayload, string $key): void
    {
        if (!\array_key_exists(key: $key, array: $transportPayload) || $transportPayload[$key] === '') {
            throw new \UnexpectedValueException(\sprintf('В payload outbox-очереди нет ключа %s.', $key));
        }
    }
}
