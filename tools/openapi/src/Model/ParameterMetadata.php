<?php

declare(strict_types=1);

namespace Tools\OpenApi\Model;

final readonly class ParameterMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
    ) {}
}
