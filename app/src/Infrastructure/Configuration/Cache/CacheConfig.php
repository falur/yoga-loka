<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Cache;

use App\Infrastructure\Configuration\TypedConfig;

final readonly class CacheConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'cache'; // @phpstan-ignore project.magicScalarLiteral
    }

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
