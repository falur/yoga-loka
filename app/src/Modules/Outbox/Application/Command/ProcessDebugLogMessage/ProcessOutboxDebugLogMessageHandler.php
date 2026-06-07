<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\ProcessDebugLogMessage;

use Psr\Log\LoggerInterface;

final readonly class ProcessOutboxDebugLogMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(ProcessOutboxDebugLogMessageCommand $command): void
    {
        $this->logger->debug(message: 'Outbox debug Job получил сообщение.', context: [
            'jobId' => $command->jobId,
            'debugText' => $command->text,
            'createdAt' => $command->createdAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
