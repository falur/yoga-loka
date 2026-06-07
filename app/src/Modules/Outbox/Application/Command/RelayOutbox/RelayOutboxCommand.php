<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Command\RelayOutbox;

final readonly class RelayOutboxCommand
{
    public function __construct(
        public int $batchSize,
        public bool $loop,
        public int $sleepSeconds,
    ) {}
}
