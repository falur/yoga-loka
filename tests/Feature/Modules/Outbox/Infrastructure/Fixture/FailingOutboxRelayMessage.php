<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Application\Message\OutboxMessage;

final readonly class FailingOutboxRelayMessage implements OutboxMessage
{
    public function __construct(
        public string $reason,
    ) {}
}
