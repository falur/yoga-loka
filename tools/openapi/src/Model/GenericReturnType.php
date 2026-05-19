<?php

declare(strict_types=1);

namespace Tools\OpenApi\Model;

final readonly class GenericReturnType
{
    public function __construct(
        public string $wrapperClass,
        public string $resourceClass,
    ) {}
}
