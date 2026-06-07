<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use Cycle\Database\DatabaseInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;

final readonly class MarkHandledDuringPushQueueConnectionProvider implements QueueConnectionProviderInterface
{
    public function __construct(
        private DatabaseInterface $database,
        private OutboxEventId $outboxEventId,
        private \DateTimeImmutable $handledAt,
    ) {}

    #[\Override]
    public function getConnection(string|null $name = null): QueueInterface
    {
        return new MarkHandledDuringPushQueue(
            database: $this->database,
            outboxEventId: $this->outboxEventId,
            handledAt: $this->handledAt,
        );
    }
}
