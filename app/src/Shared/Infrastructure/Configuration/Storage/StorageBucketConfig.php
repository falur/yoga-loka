<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

final readonly class StorageBucketConfig
{
    public function __construct(
        public string $server,
        public ?string $bucket = null,
        public ?string $distribution = null,
        public ?string $visibility = null,
        public ?string $prefix = null,
        public ?string $region = null,
    ) {}
}
