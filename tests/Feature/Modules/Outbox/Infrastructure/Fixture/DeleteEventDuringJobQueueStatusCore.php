<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use Cycle\Database\DatabaseInterface;
use Spiral\Core\CoreInterface;

/**
 * Имитирует исчезновение строки события во время выполнения Job: параллельный процесс удаляет её,
 * пока Job работает. interceptor обязан пережить это без падения — у него остаётся только снимок
 * события, прочитанный до запуска Job.
 */
final readonly class DeleteEventDuringJobQueueStatusCore implements CoreInterface
{
    public function __construct(
        private DatabaseInterface $database,
        private OutboxEventId $outboxEventId,
        private \Throwable|null $exception = null,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        $this->database
            ->delete('outbox_events')
            ->where('id', $this->outboxEventId->value())
            ->run();

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return 'job-result';
    }
}
