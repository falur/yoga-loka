<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueHeaders;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;

/**
 * @mixin \Tests\TestCase
 */
trait OutboxQueueStatusInterceptorTestHelpers
{
    private function persistQueuedEvent(string $payload = '{"text":"debug","createdAt":"2026-05-25T16:05:00+00:00"}'): StoredOutboxEvent
    {
        $now = new \DateTimeImmutable('2026-05-25 16:05:00');
        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(OutboxDebugLogRequestedEvent::class),
            payload: OutboxEventPayload::fromJson($payload),
            availableAt: $now,
            now: $now,
        );
        // Проводим событие тем же путём, что и боевой relay: publishing -> queued, через Entity.
        $storedOutboxEvent->markPublishing(availableAt: $now, now: $now);
        $storedOutboxEvent->markQueued($now);
        $this->storedOutboxEventRepository()->save($storedOutboxEvent);

        return $this->storedOutboxEventRepository()->findById($storedOutboxEvent->id)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
    }

    /**
     * StoredOutboxEvent — чистая доменная сущность без Cycle-разметки: Mapper создаёт новый
     * доменный объект на каждый findById(), поэтому мутация, которую interceptor сделал через
     * свой собственный fetch внутри process(), не видна на объекте, которым владеет тест.
     * Хелпер перечитывает актуальное состояние из репозитория перед такой проверкой.
     */
    private function reloadStoredOutboxEvent(OutboxEventId $outboxEventId): StoredOutboxEvent
    {
        return $this->storedOutboxEventRepository()->findById($outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
    }

    /**
     * @return array<string, array<string>>
     */
    private function headersFor(OutboxEventId $outboxEventId): array
    {
        return [
            OutboxQueueHeaders::OUTBOX_ID => [$outboxEventId->value()],
            OutboxQueueHeaders::OUTBOX_TYPE => [OutboxDebugLogRequestedEvent::class],
        ];
    }

    private function storedOutboxEventRepository(): StoredOutboxEventRepository
    {
        return $this->getContainer()->get(StoredOutboxEventRepository::class);
    }
}
