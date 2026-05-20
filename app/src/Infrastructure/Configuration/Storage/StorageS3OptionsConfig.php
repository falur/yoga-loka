<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Storage;

final readonly class StorageS3OptionsConfig
{
    public function __construct(
        public bool $usePathStyleEndpoint,
    ) {}
}
