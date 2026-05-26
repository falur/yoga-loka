<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Model;

final readonly class OpenApiMetadata
{
    public function __construct(public string $id, public string $description, public bool $ignore)
    {
    }
}
