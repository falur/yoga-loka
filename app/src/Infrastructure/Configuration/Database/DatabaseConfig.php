<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Database;

use App\Infrastructure\Configuration\TypedConfig;
use Cycle\Database\Config\DriverConfig;
use Cycle\Database\Config\PDOConnectionConfig;

final readonly class DatabaseConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'database';
    }

    /**
     * @param array<string, string> $aliases
     * @param array<string, DatabaseConnectionConfig> $databases
     * @param array<string, DriverConfig<PDOConnectionConfig>> $drivers
     */
    public function __construct(
        public DatabaseLoggerConfig $logger,
        public string $default,
        public array $aliases,
        public array $databases,
        public array $drivers,
    ) {}
}
