<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Scaffolder;

final readonly class ScaffolderDefaultDeclarationConfig
{
    /**
     * @param class-string|null $class
     */
    public function __construct(
        public string $namespace,
        public string $postfix,
        public string|null $class = null,
        public ScaffolderDeclarationOptionsConfig $options = new ScaffolderDeclarationOptionsConfig(),
    ) {}
}
