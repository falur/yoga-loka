<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

final readonly class StorageBucketConfig
{
    public function __construct(
        public string $server,
        public string|null $bucket = null,
        public string|null $distribution = null,
        public string|null $visibility = null,
        public string|null $prefix = null,
        public string|null $region = null,
    ) {}
}
