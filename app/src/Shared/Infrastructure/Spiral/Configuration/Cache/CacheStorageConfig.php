<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Cache;

final readonly class CacheStorageConfig
{
    public function __construct(
        public string $type,
        public string|null $driver = null,
        public string|null $path = null,
        public string|null $dsn = null,
        public string|null $namespace = null,
        public int|null $defaultLifetime = null,
    ) {}
}
