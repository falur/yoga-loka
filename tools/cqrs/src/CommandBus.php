<?php

declare(strict_types=1);

namespace Tools\Cqrs;

final readonly class CommandBus implements CommandBusInterface
{
    public function __construct(
        private CommandHandlerExecutor $commandHandlerExecutor,
    ) {}

    /**
     * @template TCommand of object
     * @template TResult
     * @param TCommand $command
     * @param callable(TCommand): TResult $handler
     * @return TResult
     */
    public function dispatch(object $command, callable $handler)
    {
        return $this->commandHandlerExecutor->execute(
            command: $command,
            handler: $handler,
        );
    }
}
