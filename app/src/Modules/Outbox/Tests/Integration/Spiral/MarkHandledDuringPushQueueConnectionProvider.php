<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;

final readonly class MarkHandledDuringPushQueueConnectionProvider implements QueueConnectionProviderInterface
{
    public function __construct(
        private StoredOutboxEventRepository $storedOutboxEventRepository,
        private OutboxEventId $outboxEventId,
        private \DateTimeImmutable $handledAt,
    ) {}

    #[\Override]
    public function getConnection(string|null $name = null): QueueInterface
    {
        return new MarkHandledDuringPushQueue(
            storedOutboxEventRepository: $this->storedOutboxEventRepository,
            outboxEventId: $this->outboxEventId,
            handledAt: $this->handledAt,
        );
    }
}
