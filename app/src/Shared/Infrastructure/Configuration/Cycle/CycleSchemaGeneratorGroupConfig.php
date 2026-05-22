<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cycle;

final readonly class CycleSchemaGeneratorGroupConfig
{
    /**
     * @param list<class-string> $classes
     */
    public function __construct(
        public array $classes,
    ) {}
}
