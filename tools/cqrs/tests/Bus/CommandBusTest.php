<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Bus;

use Cycle\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;
use Tools\Cqrs\Attribute\LogOperation;
use Tools\Cqrs\Attribute\Transactional;
use Tools\Cqrs\CommandBus;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\CommandHandlerExecutor;
use Tools\Cqrs\HandlerContext;
use Tools\Cqrs\HandlerMiddlewareInterface;
use Tools\Cqrs\HandlerMiddlewarePipeline;
use Tools\Cqrs\Middleware\LogOperationMiddleware;
use Tools\Cqrs\Middleware\TransactionalMiddleware;
use Tools\Cqrs\Tests\Support\ExecutionLog;
use Tools\Cqrs\Tests\Support\TestContainer;
use Tools\Cqrs\Tests\Support\TestLogger;

final class CommandBusTest extends TestCase
{
    public function testTransactionalHandlerRunsInsideTransaction(): void
    {
        $executionLog = new ExecutionLog();
        $commandBus = $this->commandBus(database: $this->databaseThatRunsTransactions(executionLog: $executionLog));

        $commandResult = $commandBus->dispatch(
            command: new TransactionalCommand(value: 'ok'),
            handler: (new TransactionalCommandHandler(executionLog: $executionLog))->handle(...),
        );

        self::assertSame(expected: 'ok', actual: $commandResult);
        self::assertSame(expected: [
            'transaction:begin',
            'handler:ok',
            'transaction:commit',
        ], actual: $executionLog->events());
    }

    public function testHandlerWithoutTransactionalAttributeRunsWithoutTransaction(): void
    {
        $database = $this->createMock(DatabaseInterface::class);
        $database->expects(self::never())->method('transaction');
        $commandBus = $this->commandBus(database: $database);

        self::assertSame(
            expected: 'plain',
            actual: $commandBus->dispatch(
                command: new PlainCommand(value: 'plain'),
                handler: (new PlainCommandHandler())->handle(...),
            ),
        );
    }

    public function testFailedTransactionalHandlerRethrowsOriginalException(): void
    {
        $executionLog = new ExecutionLog();
        $expectedException = new \DomainException(message: 'Ошибка команды.');
        $commandBus = $this->commandBus(database: $this->databaseThatRunsTransactions(executionLog: $executionLog));

        try {
            $commandBus->dispatch(
                command: new FailingCommand(),
                handler: (new FailingTransactionalHandler(
                    expectedException: $expectedException,
                    executionLog: $executionLog,
                ))->handle(...),
            );

            self::fail('Ожидалось исключение команды.');
        } catch (\DomainException $exception) {
            self::assertSame(expected: $expectedException, actual: $exception);
        }

        self::assertSame(expected: [
            'transaction:begin',
            'handler:fail',
            'transaction:rollback',
        ], actual: $executionLog->events());
    }

    public function testLogOperationAttributeWritesStartAndElapsedDebugLogs(): void
    {
        $logger = new TestLogger();
        $commandBus = $this->commandBus(logger: $logger);

        $commandResult = $commandBus->dispatch(
            command: new PlainCommand(value: 'logged'),
            handler: (new NamedLogCommandHandler())->handle(...),
        );

        self::assertSame(expected: 'logged', actual: $commandResult);
        self::assertCount(expectedCount: 2, haystack: $logger->messagesForLevel(level: LogLevel::DEBUG));
        self::assertSame(
            expected: '[Bus] named-command: начало',
            actual: $logger->messagesForLevel(level: LogLevel::DEBUG)[0],
        );
        self::assertStringStartsWith(
            prefix: '[Bus] named-command: ',
            string: $logger->messagesForLevel(level: LogLevel::DEBUG)[1],
        );
        self::assertStringEndsWith(
            suffix: 'ms',
            string: $logger->messagesForLevel(level: LogLevel::DEBUG)[1],
        );
    }

    public function testLogOperationWithoutNameUsesHandlerClassName(): void
    {
        $logger = new TestLogger();
        $commandBus = $this->commandBus(logger: $logger);

        $commandBus->dispatch(
            command: new PlainCommand(value: 'fallback'),
            handler: (new FallbackLogCommandHandler())->handle(...),
        );

        self::assertSame(
            expected: '[Bus] FallbackLogCommandHandler: начало',
            actual: $logger->messagesForLevel(level: LogLevel::DEBUG)[0],
        );
    }

    public function testHandlerWithoutLogOperationAttributeDoesNotWriteOperationLog(): void
    {
        $logger = new TestLogger();
        $commandBus = $this->commandBus(logger: $logger);

        $commandBus->dispatch(
            command: new PlainCommand(value: 'quiet'),
            handler: (new PlainCommandHandler())->handle(...),
        );

        self::assertSame(expected: [], actual: $logger->messagesForLevel(level: LogLevel::DEBUG));
    }

    public function testLogOperationWritesElapsedDebugLogWhenHandlerFails(): void
    {
        $logger = new TestLogger();
        $expectedException = new \DomainException(message: 'Ошибка команды.');
        $commandBus = $this->commandBus(logger: $logger);

        try {
            $commandBus->dispatch(
                command: new FailingCommand(),
                handler: (new LoggedFailingCommandHandler(expectedException: $expectedException))->handle(...),
            );

            self::fail('Ожидалось исключение команды.');
        } catch (\DomainException $exception) {
            self::assertSame(expected: $expectedException, actual: $exception);
        }

        self::assertCount(expectedCount: 2, haystack: $logger->messagesForLevel(level: LogLevel::DEBUG));
        self::assertSame(
            expected: '[Bus] failing-command: начало',
            actual: $logger->messagesForLevel(level: LogLevel::DEBUG)[0],
        );
        self::assertStringStartsWith(
            prefix: '[Bus] failing-command: ',
            string: $logger->messagesForLevel(level: LogLevel::DEBUG)[1],
        );
    }

    public function testExecutorRunsAnyMiddlewareAttribute(): void
    {
        $executionLog = new ExecutionLog();
        $commandBus = $this->commandBus();

        $commandBus->dispatch(
            command: new MiddlewareAttributeCommand(executionLog: $executionLog),
            handler: (new MiddlewareAttributeCommandHandler())->handle(...),
        );

        self::assertSame(expected: [
            'attribute:before',
            'handler',
            'attribute:after',
        ], actual: $executionLog->events());
    }

    public function testDispatchReturnsStringObjectAndVoidWithoutManualCasting(): void
    {
        $commandBus = $this->commandBus();
        $voidCommandHandler = new VoidCommandHandler();

        self::assertSame(
            expected: 'ok',
            actual: $commandBus->dispatch(
                command: new PlainCommand(value: 'ok'),
                handler: (new PlainCommandHandler())->handle(...),
            ),
        );
        self::assertInstanceOf(
            expected: CommandBusResult::class,
            actual: $commandBus->dispatch(
                command: new PlainCommand(value: 'object'),
                handler: (new ObjectCommandHandler())->handle(...),
            ),
        );

        $commandBus->dispatch(
            command: new PlainCommand(value: 'void'),
            handler: $voidCommandHandler->handle(...),
        );

        self::assertTrue(condition: $voidCommandHandler->called());
    }

    public function testDispatchDoesNotDeclareNativeMixedReturnType(): void
    {
        self::assertNull(actual: (new \ReflectionMethod(CommandBusInterface::class, 'dispatch'))->getReturnType());
        self::assertNull(actual: (new \ReflectionMethod(CommandBus::class, 'dispatch'))->getReturnType());
    }

    private function databaseWithoutTransactions(): DatabaseInterface
    {
        $database = $this->createMock(DatabaseInterface::class);
        $database->expects(self::never())->method('transaction');

        return $database;
    }

    private function commandBus(
        DatabaseInterface|null $database = null,
        TestLogger|null $logger = null,
    ): CommandBus {
        $database ??= $this->databaseWithoutTransactions();
        $logger ??= new TestLogger();

        return new CommandBus(
            commandHandlerExecutor: new CommandHandlerExecutor(
                pipeline: new HandlerMiddlewarePipeline(
                    container: new TestContainer(services: [
                        DatabaseInterface::class => $database,
                        LoggerInterface::class => $logger,
                        TransactionalMiddleware::class => new TransactionalMiddleware(database: $database),
                        LogOperationMiddleware::class => new LogOperationMiddleware(logger: $logger),
                        CustomMiddleware::class => new CustomMiddleware(),
                    ]),
                ),
            ),
        );
    }

    private function databaseThatRunsTransactions(ExecutionLog $executionLog): DatabaseInterface
    {
        $database = $this->createStub(DatabaseInterface::class);
        $database
            ->method('transaction')
            ->willReturnCallback(static function (callable $callback) use ($database, $executionLog) {
                $executionLog->add('transaction:begin');

                try {
                    $operationResult = $callback($database);
                } catch (\Throwable $exception) {
                    $executionLog->add('transaction:rollback');

                    throw $exception;
                }

                $executionLog->add('transaction:commit');

                return $operationResult;
            });

        return $database;
    }
}

final readonly class TransactionalCommand
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class PlainCommand
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class FailingCommand {}

final readonly class MiddlewareAttributeCommand
{
    public function __construct(
        public ExecutionLog $executionLog,
    ) {}
}

final readonly class TransactionalCommandHandler
{
    public function __construct(
        private ExecutionLog $executionLog,
    ) {}

    #[Transactional]
    public function handle(TransactionalCommand $command): string
    {
        $this->executionLog->add(event: \sprintf('handler:%s', $command->value));

        return $command->value;
    }
}

final readonly class PlainCommandHandler
{
    public function handle(PlainCommand $command): string
    {
        return $command->value;
    }
}

final readonly class FailingTransactionalHandler
{
    public function __construct(
        private \DomainException $expectedException,
        private ExecutionLog $executionLog,
    ) {}

    #[Transactional]
    public function handle(FailingCommand $command): string
    {
        $this->executionLog->add(event: 'handler:fail');

        throw $this->expectedException;
    }
}

final readonly class NamedLogCommandHandler
{
    #[LogOperation(name: 'named-command')]
    public function handle(PlainCommand $command): string
    {
        return $command->value;
    }
}

final readonly class FallbackLogCommandHandler
{
    #[LogOperation]
    public function handle(PlainCommand $command): string
    {
        return $command->value;
    }
}

final readonly class LoggedFailingCommandHandler
{
    public function __construct(
        private \DomainException $expectedException,
    ) {}

    #[LogOperation(name: 'failing-command')]
    public function handle(FailingCommand $command): string
    {
        throw $this->expectedException;
    }
}

final readonly class MiddlewareAttributeCommandHandler
{
    #[CustomMiddlewareAttribute]
    public function handle(MiddlewareAttributeCommand $command): void
    {
        $command->executionLog->add(event: 'handler');
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class CustomMiddlewareAttribute extends HandlerMiddlewareAttribute
{
    #[\Override]
    public function middleware(): string
    {
        return CustomMiddleware::class;
    }
}

final readonly class CustomMiddleware implements HandlerMiddlewareInterface
{
    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $next
     * @return TResult
     */
    #[\Override]
    public function handle(
        object $input,
        HandlerMiddlewareAttribute $attribute,
        callable $next,
        HandlerContext $context,
    ) {
        if (!$attribute instanceof CustomMiddlewareAttribute) {
            throw new \LogicException(message: 'Некорректный атрибут для тестового middleware.');
        }

        if (!$input instanceof MiddlewareAttributeCommand) {
            throw new \LogicException(message: 'Некорректная команда для тестового middleware-атрибута.');
        }

        $input->executionLog->add(event: 'attribute:before');

        try {
            return $next($input);
        } finally {
            $input->executionLog->add(event: 'attribute:after');
        }
    }
}

final readonly class ObjectCommandHandler
{
    public function handle(PlainCommand $command): CommandBusResult
    {
        return new CommandBusResult(value: $command->value);
    }
}

final class VoidCommandHandler
{
    private bool $called = false;

    public function handle(PlainCommand $command): void
    {
        $this->called = true;
    }

    public function called(): bool
    {
        return $this->called;
    }
}

final readonly class CommandBusResult
{
    public function __construct(
        public string $value,
    ) {}
}
