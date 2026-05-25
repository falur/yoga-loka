<?php

declare(strict_types=1);

namespace Tools\Cqrs;

interface CommandBusInterface
{
    /**
     * @template TCommand of object
     * @template TResult
     * @param TCommand $command
     * @param callable(TCommand): TResult $handler
     * @return TResult
     */
    public function dispatch(object $command, callable $handler);
}
