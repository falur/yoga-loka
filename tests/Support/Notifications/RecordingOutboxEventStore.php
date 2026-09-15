<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Тестовый дублёр IntegrationEventStoreContract: не пишет в БД, только запоминает застейдженные
 * сообщения, чтобы проверять решения рассылки (какие каналы застейджены) без реального outbox.
 */
final class RecordingOutboxEventStore implements IntegrationEventStoreContract
{
    /**
     * @var list<IntegrationEvent>
     */
    public array $messages = [];

    private int $counter = 0;

    #[\Override]
    public function add(IntegrationEvent $integrationEvent): string
    {
        $this->messages[] = $integrationEvent;
        $this->counter++;

        return \sprintf('recorded-%d', $this->counter);
    }

    /**
     * @param class-string $messageClass
     */
    public function countOf(string $messageClass): int
    {
        return \count(\array_filter(
            $this->messages,
            static fn(IntegrationEvent $message): bool => $message::class === $messageClass,
        ));
    }
}
