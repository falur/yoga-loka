<?php

declare(strict_types=1);

namespace Tools\Cqrs;

final readonly class QueryBus implements QueryBusInterface
{
    public function __construct(
        private QueryHandlerExecutor $queryHandlerExecutor,
    ) {}

    /**
     * @template TQuery of object
     * @template TResult
     * @param TQuery $query
     * @param callable(TQuery): TResult $handler
     * @return TResult
     */
    public function dispatch(object $query, callable $handler)
    {
        return $this->queryHandlerExecutor->execute(
            query: $query,
            handler: $handler,
        );
    }
}
