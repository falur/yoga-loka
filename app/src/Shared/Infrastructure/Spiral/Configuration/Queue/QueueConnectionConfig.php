<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Queue;

final readonly class QueueConnectionConfig
{
    public function __construct(
        public string $driver,
        public string|null $pipeline = null,
    ) {}
}
