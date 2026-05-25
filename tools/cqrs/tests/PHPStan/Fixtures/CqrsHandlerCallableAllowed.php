<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\PHPStan\Fixtures;

use Tools\Cqrs\Attribute\LogOperation;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\QueryBusInterface;

function allowedCqrsDispatch(
    CommandBusInterface $commandBus,
    QueryBusInterface $queryBus,
    AllowedCommandHandler $allowedCommandHandler,
    AllowedQueryHandler $allowedQueryHandler,
): void {
    $command = new AllowedCommand(value: 'create');
    $query = new AllowedQuery(value: 'read');

    $commandBus->dispatch(
        command: $command,
        handler: $allowedCommandHandler->handle(...),
    );
    $queryBus->dispatch(
        query: $query,
        handler: $allowedQueryHandler->handle(...),
    );
}

final readonly class AllowedCommand
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class AllowedQuery
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class AllowedCommandHandler
{
    #[LogOperation]
    public function handle(AllowedCommand $command): string
    {
        return $command->value;
    }
}

final readonly class AllowedQueryHandler
{
    public function handle(AllowedQuery $query): string
    {
        return $query->value;
    }
}
