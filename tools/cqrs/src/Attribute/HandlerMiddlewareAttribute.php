<?php

declare(strict_types=1);

namespace Tools\Cqrs\Attribute;

use Tools\Cqrs\HandlerMiddlewareInterface;

abstract readonly class HandlerMiddlewareAttribute
{
    /** @return class-string<HandlerMiddlewareInterface> */
    abstract public function middleware(): string;
}
