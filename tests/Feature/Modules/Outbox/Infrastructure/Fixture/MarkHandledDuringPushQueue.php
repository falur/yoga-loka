<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;

final readonly class MarkHandledDuringPushQueue implements QueueInterface
{
    public function __construct(
        private StoredOutboxEventRepository $storedOutboxEventRepository,
        private OutboxEventId $outboxEventId,
        private \DateTimeImmutable $handledAt,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function push(string $name, array $payload = [], OptionsInterface|null $options = null): string
    {
        // Имитируем sync-worker: меняем статус через ту же Entity из identity map, что держит
        // relay, — ровно как боевой OutboxQueueStatusInterceptor внутри sync-push.
        $storedOutboxEvent = $this->storedOutboxEventRepository->findById($this->outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
        $storedOutboxEvent->markHandled($this->handledAt);
        $this->storedOutboxEventRepository->save($storedOutboxEvent);

        return 'job-id';
    }
}
