<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;

/**
 * Тестовый дублёр OutboxEventStoreContract: не пишет в БД, только запоминает застейдженные
 * сообщения, чтобы проверять решения рассылки (какие каналы застейджены) без реального outbox.
 */
final class RecordingOutboxEventStore implements OutboxEventStoreContract
{
    /**
     * @var list<OutboxMessage>
     */
    public array $messages = [];

    private int $counter = 0;

    #[\Override]
    public function add(OutboxMessage $outboxMessage): StoredOutboxEventId
    {
        $this->messages[] = $outboxMessage;
        $this->counter++;

        return StoredOutboxEventId::fromString(\sprintf('recorded-%d', $this->counter));
    }

    /**
     * @param class-string $messageClass
     */
    public function countOf(string $messageClass): int
    {
        return \count(\array_filter(
            $this->messages,
            static fn(OutboxMessage $message): bool => $message::class === $messageClass,
        ));
    }
}
