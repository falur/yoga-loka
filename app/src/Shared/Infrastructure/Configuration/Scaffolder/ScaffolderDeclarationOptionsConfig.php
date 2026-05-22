<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Scaffolder;

final readonly class ScaffolderDeclarationOptionsConfig
{
    public function __construct(
        public ?string $directory = null,
        public ?string $annotated = null,
    ) {}
}
