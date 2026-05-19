<?php

declare(strict_types=1);

namespace Tools\OpenApi\Model;

final readonly class SourceFile
{
    public function __construct(
        public string $path,
    ) {}
}
