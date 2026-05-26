<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Spec;

final readonly class OpenApiGenerationResult
{
    public function __construct(public string $outputFile, public int $operationCount, public int $schemaCount)
    {
    }
}
