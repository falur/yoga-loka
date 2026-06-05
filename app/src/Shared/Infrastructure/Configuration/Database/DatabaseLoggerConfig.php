<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Database;

final readonly class DatabaseLoggerConfig
{
    /**
     * @param array<string, string> $drivers
     */
    public function __construct(
        public string|null $default,
        public array $drivers,
    ) {}
}
