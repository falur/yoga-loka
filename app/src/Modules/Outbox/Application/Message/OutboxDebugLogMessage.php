<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Message;

final readonly class OutboxDebugLogMessage implements OutboxMessage
{
    public function __construct(
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {}
}
