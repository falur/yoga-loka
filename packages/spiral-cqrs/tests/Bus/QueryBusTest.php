<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Tests\Bus;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\HandlerMiddlewarePipeline;
use GianTiaga\SpiralCqrs\Middleware\LogOperationMiddleware;
use GianTiaga\SpiralCqrs\QueryBus;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralCqrs\QueryHandlerExecutor;
use GianTiaga\SpiralCqrs\Tests\Support\TestContainer;
use GianTiaga\SpiralCqrs\Tests\Support\TestLogger;

final class QueryBusTest extends TestCase
{
    public function testQueryBusPassesQueryToHandlerAndReturnsResult(): void
    {
        $queryBus = $this->queryBus();

        $queryResult = $queryBus->dispatch(
            query: new ExampleQuery(value: 'found'),
            handler: (new ExampleQueryHandler())->handle(...),
        );

        self::assertSame(expected: 'found', actual: $queryResult->value);
    }

    public function testQueryBusWritesOperationLogOnlyWhenAttributeExists(): void
    {
        $logger = new TestLogger();
        $queryBus = $this->queryBus(logger: $logger);

        $queryBus->dispatch(
            query: new ExampleQuery(value: 'found'),
            handler: (new LoggedQueryHandler())->handle(...),
        );

        self::assertCount(expectedCount: 2, haystack: $logger->messagesForLevel(level: LogLevel::DEBUG));
        self::assertSame(
            expected: '[Bus] LoggedQueryHandler: начало',
            actual: $logger->messagesForLevel(level: LogLevel::DEBUG)[0],
        );

        $quietLogger = new TestLogger();
        $quietQueryBus = $this->queryBus(logger: $quietLogger);
        $quietQueryBus->dispatch(
            query: new ExampleQuery(value: 'quiet'),
            handler: (new ExampleQueryHandler())->handle(...),
        );

        self::assertSame(expected: [], actual: $quietLogger->messagesForLevel(level: LogLevel::DEBUG));
    }

    public function testDispatchDoesNotDeclareNativeMixedReturnType(): void
    {
        self::assertNull(actual: (new \ReflectionMethod(QueryBusInterface::class, 'dispatch'))->getReturnType());
        self::assertNull(actual: (new \ReflectionMethod(QueryBus::class, 'dispatch'))->getReturnType());
    }

    private function queryBus(?TestLogger $logger = null): QueryBus
    {
        $logger ??= new TestLogger();

        return new QueryBus(
            queryHandlerExecutor: new QueryHandlerExecutor(
                pipeline: new HandlerMiddlewarePipeline(
                    container: new TestContainer(services: [
                        LoggerInterface::class => $logger,
                        LogOperationMiddleware::class => new LogOperationMiddleware(
                            logger: $logger,
                        ),
                    ]),
                ),
            ),
        );
    }
}

final readonly class ExampleQuery
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class QueryBusResult
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class ExampleQueryHandler
{
    public function handle(ExampleQuery $query): QueryBusResult
    {
        return new QueryBusResult(value: $query->value);
    }
}

final readonly class LoggedQueryHandler
{
    #[LogOperation]
    public function handle(ExampleQuery $query): QueryBusResult
    {
        return new QueryBusResult(value: $query->value);
    }
}
