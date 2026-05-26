<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Attribute;

use GianTiaga\SpiralCqrs\HandlerMiddlewareInterface;

abstract readonly class HandlerMiddlewareAttribute
{
    /** @return class-string<HandlerMiddlewareInterface> */
    abstract public function middleware(): string;
}
