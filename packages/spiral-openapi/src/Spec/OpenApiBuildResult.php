<?php

declare(strict_types=1);

namespace GianTiaga\SpiralOpenApi\Spec;

final readonly class OpenApiBuildResult
{
    /**
     * @param array<string, mixed> $spec
     */
    public function __construct(
        public array $spec,
        public int $operationCount,
        public int $schemaCount,
    ) {}
}
