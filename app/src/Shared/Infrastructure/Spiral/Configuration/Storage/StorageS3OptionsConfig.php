<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Storage;

final readonly class StorageS3OptionsConfig
{
    public function __construct(
        public bool $usePathStyleEndpoint,
    ) {}
}
