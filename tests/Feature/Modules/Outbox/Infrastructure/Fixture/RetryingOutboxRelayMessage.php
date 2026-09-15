<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final readonly class RetryingOutboxRelayMessage implements IntegrationEvent
{
    public function __construct(
        public string $reason,
    ) {}
}
