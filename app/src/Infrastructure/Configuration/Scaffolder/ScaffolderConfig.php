<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Scaffolder;

use App\Infrastructure\Configuration\TypedConfig;

final readonly class ScaffolderConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'scaffolder';
    }

    /**
     * @param list<string> $header
     * @param array<string, ScaffolderDeclarationConfig> $declarations
     */
    public function __construct(
        public array $header,
        public string $directory,
        public string $namespace,
        public array $declarations,
        public ScaffolderDefaultsConfig $defaults,
    ) {}
}
