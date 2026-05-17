<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Cache;

final readonly class CacheConfig
{
    /**
     * @param array<string, CacheAliasConfig|string> $aliases
     * @param array<string, CacheStorageConfig> $storages
     * @param array<string, string> $typeAliases
     */
    public function __construct(
        public string $default,
        public array $aliases,
        public array $storages,
        public array $typeAliases,
    ) {}
}
