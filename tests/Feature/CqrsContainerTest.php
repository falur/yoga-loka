<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\QueryBusInterface;

final class CqrsContainerTest extends TestCase
{
    public function testCqrsServicesAreAvailableFromContainer(): void
    {
        $container = $this->getContainer();

        $commandBus = $container->get(CommandBusInterface::class);
        $queryBus = $container->get(QueryBusInterface::class);

        self::assertInstanceOf(CommandBusInterface::class, $commandBus);
        self::assertInstanceOf(QueryBusInterface::class, $queryBus);
        self::assertSame(
            'command ok',
            $commandBus->dispatch(
                command: new CqrsContainerCommand(value: 'command ok'),
                handler: (new CqrsContainerCommandHandler())->handle(...),
            ),
        );
        self::assertSame(
            'query ok',
            $queryBus->dispatch(
                query: new CqrsContainerQuery(value: 'query ok'),
                handler: (new CqrsContainerQueryHandler())->handle(...),
            ),
        );
    }
}

final readonly class CqrsContainerCommand
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class CqrsContainerQuery
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class CqrsContainerCommandHandler
{
    public function handle(CqrsContainerCommand $command): string
    {
        return $command->value;
    }
}

final readonly class CqrsContainerQueryHandler
{
    public function handle(CqrsContainerQuery $query): string
    {
        return $query->value;
    }
}
