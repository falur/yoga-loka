<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Attribute;

use GianTiaga\SpiralCqrs\Middleware\LogOperationMiddleware;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class LogOperation extends HandlerMiddlewareAttribute
{
    public function __construct(
        public string|null $name = null,
    ) {}

    #[\Override]
    public function middleware(): string
    {
        return LogOperationMiddleware::class;
    }
}
