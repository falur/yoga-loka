<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Database;

final readonly class DatabaseConnectionConfig
{
    public function __construct(
        public string $driver,
    ) {}
}
