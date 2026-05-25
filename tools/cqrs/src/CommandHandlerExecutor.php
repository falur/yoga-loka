<?php

declare(strict_types=1);

namespace Tools\Cqrs;

/**
 * @internal
 */
final readonly class CommandHandlerExecutor
{
    public function __construct(
        private HandlerMiddlewarePipeline $pipeline,
    ) {}

    /**
     * @template TCommand of object
     * @template TResult
     * @param TCommand $command
     * @param callable(TCommand): TResult $handler
     * @return TResult
     */
    public function execute(
        object $command,
        callable $handler,
    ) {
        return $this->pipeline->execute(
            input: $command,
            handler: $handler,
            context: new CommandHandlerContext(
                handlerReflection: $this->handlerReflection(handler: $handler),
            ),
        );
    }

    private function handlerReflection(callable $handler): \ReflectionFunction
    {
        return new \ReflectionFunction(\Closure::fromCallable($handler));
    }
}
