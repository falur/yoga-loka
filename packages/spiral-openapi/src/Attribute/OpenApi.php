<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OpenApi
{
    public function __construct(public string $id = '', public string $description = '', public bool $ignore = false) {}
}
