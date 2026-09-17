<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final readonly class UnregisteredOutboxRelayMessage implements IntegrationEvent
{
    public function __construct(
        public string $reason,
    ) {}
}
