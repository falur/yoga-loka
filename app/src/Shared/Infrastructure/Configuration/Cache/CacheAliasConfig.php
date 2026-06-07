<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cache;

final readonly class CacheAliasConfig
{
    public function __construct(
        public string $storage,
        public string|null $prefix = null,
    ) {}
}
