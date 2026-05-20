<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Queue;

final readonly class QueueRegistryConfig
{
    /**
     * @param array<string, class-string> $handlers
     * @param array<string, string> $serializers
     */
    public function __construct(
        public array $handlers,
        public array $serializers,
    ) {}
}
