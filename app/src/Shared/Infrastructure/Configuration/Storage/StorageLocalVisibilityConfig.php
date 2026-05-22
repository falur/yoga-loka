<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

final readonly class StorageLocalVisibilityConfig
{
    public function __construct(
        public StorageVisibilityModeConfig $public,
        public StorageVisibilityModeConfig $private,
        public string $default,
    ) {}
}
