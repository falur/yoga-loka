<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use Cycle\Database\DatabaseInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;

/**
 * Имитирует исчезновение строки события во время push: параллельный процесс (чистка, ручное
 * вмешательство) удаляет её между захватом relay и возвратом из push. Строка удаляется напрямую
 * через DatabaseInterface, потому что доменный StoredOutboxEventRepository удаления не объявляет —
 * удаление строки снаружи это и есть моделируемая аварийная ситуация.
 */
final readonly class DeleteEventDuringPushQueue implements QueueInterface
{
    public function __construct(
        private DatabaseInterface $database,
        private OutboxEventId $outboxEventId,
        private \Throwable|null $exception = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function push(string $name, array $payload = [], OptionsInterface|null $options = null): string
    {
        $this->database
            ->delete('outbox_events')
            ->where('id', $this->outboxEventId->value())
            ->run();

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return 'job-id';
    }
}
