<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\ProcessDebugLogMessage;

final readonly class ProcessOutboxDebugLogMessageCommand
{
    public function __construct(
        public string $text,
        public \DateTimeImmutable $createdAt,
        public string $jobId,
    ) {}
}
