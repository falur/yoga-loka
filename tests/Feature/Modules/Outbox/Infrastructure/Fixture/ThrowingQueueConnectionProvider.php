<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;

final readonly class ThrowingQueueConnectionProvider implements QueueConnectionProviderInterface
{
    public function __construct(
        private string $exceptionMessage = 'RabbitMQ недоступен.',
    ) {}

    #[\Override]
    public function getConnection(string|null $name = null): QueueInterface
    {
        return new ThrowingQueue($this->exceptionMessage);
    }
}
