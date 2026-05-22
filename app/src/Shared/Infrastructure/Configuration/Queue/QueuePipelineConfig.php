<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Queue;

use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

final readonly class QueuePipelineConfig
{
    public function __construct(
        public CreateInfoInterface $connector,
        public bool $consume,
    ) {}
}
