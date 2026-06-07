<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Infrastructure\OutboxQueueHeaders;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;

/**
 * @mixin \Tests\TestCase
 */
trait OutboxQueueStatusInterceptorTestHelpers
{
    private function persistQueuedEvent(string $payload = '{"text":"debug","createdAt":"2026-05-25T16:05:00+00:00"}'): StoredOutboxEvent
    {
        $now = new \DateTimeImmutable('2026-05-25 16:05:00');
        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(OutboxDebugLogMessage::class),
            payload: OutboxEventPayload::fromJson($payload),
            availableAt: $now,
            now: $now,
        );
        // queued-статус выставляем тем же CAS-переходом, что и боевой relay: publishing -> queued.
        $storedOutboxEvent->markPublishing(availableAt: $now, now: $now);
        $this->entityManager()->persist($storedOutboxEvent);
        $this->entityManager()->run();
        $this->outboxEventRepository()->markQueuedIfPublishing(outboxEventId: $storedOutboxEvent->id, now: $now);
        // CAS-переход publishing -> queued выполняется сырым UPDATE мимо ORM, поэтому identity map
        // держит stale-сущность со статусом Publishing. Очищаем heap, чтобы findById перегидрировал
        // строку из БД и вернул актуальный статус Queued.
        $this->cleanOrmState();

        return $this->outboxEventRepository()->findById($storedOutboxEvent->id)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
    }

    /**
     * @return array<string, array<string>>
     */
    private function headersFor(OutboxEventId $outboxEventId): array
    {
        return [
            OutboxQueueHeaders::OUTBOX_ID => [$outboxEventId->value()],
            OutboxQueueHeaders::OUTBOX_TYPE => [OutboxDebugLogMessage::class],
        ];
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function cleanOrmState(): void
    {
        $this->entityManager()->clean();
        $this->getContainer()->get(ORMInterface::class)->getHeap()->clean();
    }

    private function outboxEventRepository(): OutboxEventRepository
    {
        return $this->getContainer()->get(OutboxEventRepository::class);
    }
}
