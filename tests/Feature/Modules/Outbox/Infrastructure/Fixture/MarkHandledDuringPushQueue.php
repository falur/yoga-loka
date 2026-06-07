<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Shared\Infrastructure\Database\DatabaseDateTimeFormat;
use Cycle\Database\DatabaseInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;

final readonly class MarkHandledDuringPushQueue implements QueueInterface
{
    public function __construct(
        private DatabaseInterface $database,
        private OutboxEventId $outboxEventId,
        private \DateTimeImmutable $handledAt,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function push(string $name, array $payload = [], OptionsInterface|null $options = null): string
    {
        $this->database
            ->update('outbox_events')
            ->values([
                'status' => OutboxEventStatus::Handled->value,
                'queued_at' => $this->handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'handled_at' => $this->handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'updated_at' => $this->handledAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->where('id', $this->outboxEventId->value())
            ->run();

        return 'job-id';
    }
}
