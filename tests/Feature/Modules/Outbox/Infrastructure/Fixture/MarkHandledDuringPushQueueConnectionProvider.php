<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;

final readonly class MarkHandledDuringPushQueueConnectionProvider implements QueueConnectionProviderInterface
{
    public function __construct(
        private OutboxEventRepository $outboxEventRepository,
        private EntityManagerInterface $entityManager,
        private OutboxEventId $outboxEventId,
        private \DateTimeImmutable $handledAt,
    ) {}

    #[\Override]
    public function getConnection(string|null $name = null): QueueInterface
    {
        return new MarkHandledDuringPushQueue(
            outboxEventRepository: $this->outboxEventRepository,
            entityManager: $this->entityManager,
            outboxEventId: $this->outboxEventId,
            handledAt: $this->handledAt,
        );
    }
}
