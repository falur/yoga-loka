<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Model;

final readonly class FileResponseMetadata
{
    public function __construct(public string $responseClass, public string $contentType, public bool $binary) {}
}
