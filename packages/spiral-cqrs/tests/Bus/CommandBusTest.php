<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Tests\Bus;

use Cycle\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralCqrs\CommandBus;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\CommandHandlerExecutor;
use GianTiaga\SpiralCqrs\HandlerContext;
use GianTiaga\SpiralCqrs\HandlerMiddlewareInterface;
use GianTiaga\SpiralCqrs\HandlerMiddlewarePipeline;
use GianTiaga\SpiralCqrs\Middleware\LogOperationMiddleware;
use GianTiaga\SpiralCqrs\Middleware\TransactionalMiddleware;
use GianTiaga\SpiralCqrs\Tests\Support\ExecutionLog;
use GianTiaga\SpiralCqrs\Tests\Support\TestContainer;
use GianTiaga\SpiralCqrs\Tests\Support\TestLogger;

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

    public function testInvalidHandlerMiddlewareUsesRussianMessage(): void
    {
        $commandBus = new CommandBus(
            commandHandlerExecutor: new CommandHandlerExecutor(
                pipeline: new HandlerMiddlewarePipeline(
                    container: new TestContainer(services: [
                        InvalidMiddleware::class => new InvalidMiddleware(),
                    ]),
                ),
            ),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Промежуточный обработчик должен реализовывать HandlerMiddlewareInterface.');

        $commandBus->dispatch(
            command: new InvalidMiddlewareCommand(),
            handler: (new InvalidMiddlewareCommandHandler())->handle(...),
        );
    }

    public function testInvalidMiddlewareAttributesUseRussianMessages(): void
    {
        $context = new HandlerContext(handlerReflection: new \ReflectionFunction(static fn(object $input): object => $input));

        $this->assertMiddlewareExceptionMessage(
            expectedMessage: 'Некорректный атрибут для промежуточного обработчика логирования операции.',
            callback: static fn() => new LogOperationMiddleware(
                logger: new TestLogger(),
            )->handle(
                input: new PlainCommand(value: 'test'),
                attribute: new CustomMiddlewareAttribute(),
                next: static fn(object $input): object => $input,
                context: $context,
            ),
        );
        $this->assertMiddlewareExceptionMessage(
            expectedMessage: 'Некорректный атрибут для транзакционного промежуточного обработчика.',
            callback: fn() => new TransactionalMiddleware(
                database: $this->databaseWithoutTransactions(),
            )->handle(
                input: new PlainCommand(value: 'test'),
                attribute: new CustomMiddlewareAttribute(),
                next: static fn(object $input): object => $input,
                context: $context,
            ),
        );
        $this->assertMiddlewareExceptionMessage(
            expectedMessage: 'Атрибут #[Transactional] поддерживается только для обработчика команды.',
            callback: fn() => new TransactionalMiddleware(
                database: $this->databaseWithoutTransactions(),
            )->handle(
                input: new PlainCommand(value: 'test'),
                attribute: new Transactional(),
                next: static fn(object $input): object => $input,
                context: $context,
            ),
        );
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
        ?DatabaseInterface $database = null,
        ?TestLogger $logger = null,
    ): CommandBus {
        $database ??= $this->databaseWithoutTransactions();
        $logger ??= new TestLogger();

        return new CommandBus(
            commandHandlerExecutor: new CommandHandlerExecutor(
                pipeline: new HandlerMiddlewarePipeline(
                    container: new TestContainer(services: [
                        DatabaseInterface::class => $database,
                        LoggerInterface::class => $logger,
                        TransactionalMiddleware::class => new TransactionalMiddleware(
                            database: $database,
                        ),
                        LogOperationMiddleware::class => new LogOperationMiddleware(
                            logger: $logger,
                        ),
                        CustomMiddleware::class => new CustomMiddleware(),
                    ]),
                ),
            ),
        );
    }

    private function assertMiddlewareExceptionMessage(string $expectedMessage, callable $callback): void
    {
        try {
            $callback();

            self::fail('Ожидалось исключение промежуточного обработчика.');
        } catch (\LogicException $exception) {
            self::assertSame(expected: $expectedMessage, actual: $exception->getMessage());
        }
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

final readonly class InvalidMiddlewareCommand {}

final readonly class InvalidMiddlewareCommandHandler
{
    #[InvalidMiddlewareAttribute]
    public function handle(InvalidMiddlewareCommand $command): void {}
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

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class InvalidMiddlewareAttribute extends HandlerMiddlewareAttribute
{
    #[\Override]
    public function middleware(): string
    {
        return InvalidMiddleware::class;
    }
}

final readonly class InvalidMiddleware {}

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
            throw new \LogicException(message: 'Некорректный атрибут для тестового промежуточного обработчика.');
        }

        if (!$input instanceof MiddlewareAttributeCommand) {
            throw new \LogicException(message: 'Некорректная команда для тестового атрибута промежуточного обработчика.');
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
