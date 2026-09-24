<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use GianTiaga\SpiralOutbox\IntegrationEventContract;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;

/**
 * Тестовый дублёр OutboxEventStoreContract: не пишет в БД, только запоминает записанные события,
 * чтобы проверять решения рассылки (какие каналы застейджены) без реальных таблиц обмена.
 */
final class RecordingOutboxEventStore implements OutboxEventStoreContract
{
    /**
     * @var list<IntegrationEventContract>
     */
    public array $messages = [];

    private int $counter = 0;

    #[\Override]
    public function add(IntegrationEventContract $event): string
    {
        $this->messages[] = $event;
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
            static fn(IntegrationEventContract $message): bool => $message::class === $messageClass,
        ));
    }
}
