<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Storage;

final readonly class StorageVisibilityModeConfig
{
    public function __construct(
        public int $file,
        public int $dir,
    ) {}
}
