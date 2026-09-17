<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Job;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageCommand;
use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\JobHandler;

final class OutboxDebugLogJob extends JobHandler
{
    public function invoke(
        OutboxEnvelopeDto $payload,
        string $id,
        IntegrationEventLoaderContract $integrationEventLoader,
        CommandBusInterface $commandBus,
        ProcessOutboxDebugLogMessageHandler $processOutboxDebugLogMessageHandler,
    ): void {
        $outboxDebugLogMessage = $integrationEventLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedEventClass: OutboxDebugLogRequestedEvent::class,
        );

        $commandBus->dispatch(
            command: new ProcessOutboxDebugLogMessageCommand(
                text: $outboxDebugLogMessage->text,
                createdAt: $outboxDebugLogMessage->createdAt,
                jobId: $id,
            ),
            handler: $processOutboxDebugLogMessageHandler->handle(...),
        );
    }
}
