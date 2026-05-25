<?php

declare(strict_types=1);

namespace Tools\Cqrs;

/**
 * @internal
 */
final readonly class QueryHandlerExecutor
{
    public function __construct(
        private HandlerMiddlewarePipeline $pipeline,
    ) {}

    /**
     * @template TQuery of object
     * @template TResult
     * @param TQuery $query
     * @param callable(TQuery): TResult $handler
     * @return TResult
     */
    public function execute(object $query, callable $handler)
    {
        return $this->pipeline->execute(
            input: $query,
            handler: $handler,
            context: new HandlerContext(
                handlerReflection: $this->handlerReflection(handler: $handler),
            ),
        );
    }

    private function handlerReflection(callable $handler): \ReflectionFunction
    {
        return new \ReflectionFunction(\Closure::fromCallable($handler));
    }
}
