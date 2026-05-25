<?php

declare(strict_types=1);

namespace Tools\Cqrs;

use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;

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
