<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\RelayOutbox;

final readonly class RelayOutboxResult
{
    public function __construct(
        public int $processedCount,
    ) {}
}
