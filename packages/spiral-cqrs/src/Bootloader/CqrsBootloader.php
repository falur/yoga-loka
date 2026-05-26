<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use GianTiaga\SpiralCqrs\CommandBus;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBus;
use GianTiaga\SpiralCqrs\QueryBusInterface;

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
