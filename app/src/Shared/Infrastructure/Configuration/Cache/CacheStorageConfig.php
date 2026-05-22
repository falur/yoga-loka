<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cache;

final readonly class CacheStorageConfig
{
    public function __construct(
        public string $type,
        public ?string $driver = null,
        public ?string $path = null,
        public ?string $dsn = null,
        public ?string $namespace = null,
        public ?int $defaultLifetime = null,
    ) {}
}
