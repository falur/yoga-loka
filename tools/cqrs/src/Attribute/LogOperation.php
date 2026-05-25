<?php

declare(strict_types=1);

namespace Tools\Cqrs\Attribute;

use Tools\Cqrs\Middleware\LogOperationMiddleware;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class LogOperation extends HandlerMiddlewareAttribute
{
    public function __construct(
        public ?string $name = null,
    ) {}

    #[\Override]
    public function middleware(): string
    {
        return LogOperationMiddleware::class;
    }
}
