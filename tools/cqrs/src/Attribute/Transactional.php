<?php

declare(strict_types=1);

namespace Tools\Cqrs\Attribute;

use Tools\Cqrs\Middleware\TransactionalMiddleware;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Transactional extends HandlerMiddlewareAttribute
{
    #[\Override]
    public function middleware(): string
    {
        return TransactionalMiddleware::class;
    }
}
