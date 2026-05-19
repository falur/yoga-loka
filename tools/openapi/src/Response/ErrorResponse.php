<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

final class ErrorResponse extends AbstractJsonResponse
{
    public function __construct(
        public readonly string $message,
        public readonly ?int $code = null,
    ) {}
}
