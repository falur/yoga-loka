<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Scaffolder;

final readonly class ScaffolderDeclarationConfig
{
    public function __construct(
        public string $namespace,
    ) {}
}
