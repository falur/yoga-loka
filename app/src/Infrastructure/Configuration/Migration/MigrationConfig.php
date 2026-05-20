<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Migration;

use App\Infrastructure\Configuration\TypedConfig;

final readonly class MigrationConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'migration';
    }

    /**
     * @param array<string, string> $vendorDirectories
     */
    public function __construct(
        public string $directory,
        public array $vendorDirectories,
        public string $strategy,
        public string $nameGenerator,
        public string $table,
        public bool $safe,
    ) {}
}
