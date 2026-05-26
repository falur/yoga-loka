<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs;

use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;

interface HandlerMiddlewareInterface
{
    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $next
     * @return TResult
     */
    public function handle(
        object $input,
        HandlerMiddlewareAttribute $attribute,
        callable $next,
        HandlerContext $context,
    );
}
