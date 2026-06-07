<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use Spiral\Core\CoreInterface;
use Tools\Cqrs\CommandBusInterface;

final readonly class QueueStatusDebugLogJobCore implements CoreInterface
{
    public function __construct(
        private OutboxDebugLogJob $outboxDebugLogJob,
        private OutboxQueueEnvelope $payload,
        private OutboxMessageLoaderContract $outboxMessageLoader,
        private CommandBusInterface $commandBus,
        private ProcessOutboxDebugLogMessageHandler $processOutboxDebugLogMessageHandler,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        $this->outboxDebugLogJob->invoke(
            payload: $this->payload,
            id: 'job-id',
            outboxMessageLoader: $this->outboxMessageLoader,
            commandBus: $this->commandBus,
            processOutboxDebugLogMessageHandler: $this->processOutboxDebugLogMessageHandler,
        );

        return null;
    }
}
