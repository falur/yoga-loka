<?php

declare(strict_types=1);

namespace Tools\Cqrs\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Tools\Cqrs\CommandBus;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\QueryBus;
use Tools\Cqrs\QueryBusInterface;

final class CqrsBootloader extends Bootloader
{
    #[\Override]
    public function defineBindings(): array
    {
        return [
            ...parent::defineBindings(),
            CommandBusInterface::class => CommandBus::class,
            QueryBusInterface::class => QueryBus::class,
        ];
    }
}
