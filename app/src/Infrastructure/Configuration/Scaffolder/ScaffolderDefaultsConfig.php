<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Scaffolder;

final readonly class ScaffolderDefaultsConfig
{
    /**
     * @param array<string, ScaffolderDefaultDeclarationConfig> $declarations
     */
    public function __construct(
        public array $declarations,
    ) {}
}
