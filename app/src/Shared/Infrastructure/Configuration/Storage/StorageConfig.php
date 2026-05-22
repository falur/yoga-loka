<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class StorageConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'storage';
    }

    /**
     * @param array<string, StorageServerConfig> $servers
     * @param array<string, StorageBucketConfig> $buckets
     */
    public function __construct(
        public string $default,
        public array $servers,
        public array $buckets,
    ) {}
}
