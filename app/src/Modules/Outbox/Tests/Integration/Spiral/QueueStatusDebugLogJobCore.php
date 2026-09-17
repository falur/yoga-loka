<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Core\CoreInterface;

final readonly class QueueStatusDebugLogJobCore implements CoreInterface
{
    public function __construct(
        private OutboxDebugLogJob $outboxDebugLogJob,
        private OutboxEnvelopeDto $payload,
        private IntegrationEventLoaderContract $integrationEventLoader,
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
            integrationEventLoader: $this->integrationEventLoader,
            commandBus: $this->commandBus,
            processOutboxDebugLogMessageHandler: $this->processOutboxDebugLogMessageHandler,
        );

        return null;
    }
}
