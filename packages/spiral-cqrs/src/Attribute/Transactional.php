<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Attribute;

use GianTiaga\SpiralCqrs\Middleware\TransactionalMiddleware;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Transactional extends HandlerMiddlewareAttribute
{
    #[\Override]
    public function middleware(): string
    {
        return TransactionalMiddleware::class;
    }
}
