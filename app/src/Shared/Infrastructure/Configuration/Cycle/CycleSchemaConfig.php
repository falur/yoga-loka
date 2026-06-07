<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cycle;

final readonly class CycleSchemaConfig
{
    /**
     * @param array<string, class-string|object> $defaults
     * @param list<class-string>|array<string, CycleSchemaGeneratorGroupConfig>|null $generators
     */
    public function __construct(
        public bool $cache,
        public array $defaults,
        public CycleCollectionsConfig $collections,
        public array|null $generators,
    ) {}
}
