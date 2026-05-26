<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs;

interface QueryBusInterface
{
    /**
     * @template TQuery of object
     * @template TResult
     * @param TQuery $query
     * @param callable(TQuery): TResult $handler
     * @return TResult
     */
    public function dispatch(object $query, callable $handler);
}
