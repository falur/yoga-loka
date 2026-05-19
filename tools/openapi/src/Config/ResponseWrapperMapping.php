<?php

declare(strict_types=1);

namespace Tools\OpenApi\Config;

final readonly class ResponseWrapperMapping
{
    public function __construct(
        public string $dataResponseClass,
        public string $collectionResponseClass,
        public string $paginationResponseClass,
        public string $errorResponseClass,
    ) {}
}
