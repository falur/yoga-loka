<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

final readonly class StorageServerConfig
{
    public function __construct(
        public string $adapter,
        public ?string $directory = null,
        public StorageLocalVisibilityConfig|string|null $visibility = null,
        public ?string $region = null,
        public ?string $version = null,
        public ?string $bucket = null,
        public ?string $key = null,
        public ?string $secret = null,
        public ?string $token = null,
        public ?string $expires = null,
        public ?string $prefix = null,
        public ?string $endpoint = null,
        public ?StorageS3OptionsConfig $options = null,
    ) {}
}
