<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Model;

final readonly class SourceFile
{
    public function __construct(public string $path)
    {
    }
}
