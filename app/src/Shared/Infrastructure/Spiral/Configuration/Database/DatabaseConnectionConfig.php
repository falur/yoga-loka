<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Database;

final readonly class DatabaseConnectionConfig
{
    public function __construct(
        public string $driver,
    ) {}
}
