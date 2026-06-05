<?php

declare(strict_types=1);

namespace Tools\OpenApi\Model;

final readonly class RouteMetadata
{
    /**
     * @param list<string> $methods
     * @param list<string> $middleware
     */
    public function __construct(
        public string $path,
        public string|null $name,
        public array $methods,
        public string|null $group,
        public array $middleware,
        public int $priority,
    ) {}
}
