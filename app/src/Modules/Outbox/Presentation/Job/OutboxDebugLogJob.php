<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Presentation\Job;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageCommand;
use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use Spiral\Queue\JobHandler;
use Tools\Cqrs\CommandBusInterface;

final class OutboxDebugLogJob extends JobHandler
{
    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        ProcessOutboxDebugLogMessageHandler $processOutboxDebugLogMessageHandler,
    ): void {
        $outboxDebugLogMessage = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
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
