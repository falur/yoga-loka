<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Message;

use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\OutboxMessageLoadingException;
use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Repository\OutboxEventRepository;

final readonly class OutboxMessageLoader implements OutboxMessageLoaderContract
{
    public function __construct(
        private OutboxEventRepository $outboxEventRepository,
        private OutboxMessageSerializerContract $outboxMessageSerializer,
    ) {}

    /**
     * @template TOutboxMessage of OutboxMessage
     * @param class-string<TOutboxMessage> $expectedMessageClass
     * @return TOutboxMessage
     */
    #[\Override]
    public function load(OutboxEventId $outboxEventId, string $expectedMessageClass): OutboxMessage
    {
        $storedOutboxEvent = $this->outboxEventRepository->findById($outboxEventId)
            ?? throw OutboxMessageLoadingException::eventNotFound(
                outboxEventId: $outboxEventId,
                expectedMessageClass: $expectedMessageClass,
            );

        if ($storedOutboxEvent->type->value() !== $expectedMessageClass) {
            throw OutboxMessageLoadingException::storedTypeMismatch(
                storedMessageClass: $storedOutboxEvent->type->value(),
                expectedMessageClass: $expectedMessageClass,
            );
        }

        try {
            $outboxMessage = $this->outboxMessageSerializer->deserialize(
                serializedOutboxMessage: new SerializedOutboxMessage(
                    type: $storedOutboxEvent->type->value(),
                    payload: $storedOutboxEvent->payload->value(),
                ),
            );
        } catch (\Throwable $exception) {
            throw OutboxMessageLoadingException::deserializationFailed(
                expectedMessageClass: $expectedMessageClass,
                exception: $exception,
            );
        }

        if (!$outboxMessage instanceof $expectedMessageClass) {
            throw OutboxMessageLoadingException::restoredTypeMismatch(
                outboxMessage: $outboxMessage,
                expectedMessageClass: $expectedMessageClass,
            );
        }

        return $outboxMessage;
    }
}
