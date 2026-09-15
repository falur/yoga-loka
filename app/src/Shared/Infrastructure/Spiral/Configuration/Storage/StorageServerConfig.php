<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Storage;

final readonly class StorageServerConfig
{
    public function __construct(
        public string $adapter,
        public string|null $directory = null,
        public StorageLocalVisibilityConfig|string|null $visibility = null,
        public string|null $region = null,
        public string|null $version = null,
        public string|null $bucket = null,
        public string|null $key = null,
        public string|null $secret = null,
        public string|null $token = null,
        public string|null $expires = null,
        public string|null $prefix = null,
        public string|null $endpoint = null,
        public StorageS3OptionsConfig|null $options = null,
    ) {}
}
