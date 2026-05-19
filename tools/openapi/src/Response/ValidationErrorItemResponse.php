<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

final readonly class ValidationErrorItemResponse
{
    public function __construct(
        public string $field,
        public string $message,
    ) {}
}
