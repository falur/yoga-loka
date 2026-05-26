<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Tests\Bus;

use PHPUnit\Framework\TestCase;
use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
use GianTiaga\SpiralCqrs\CommandBus;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBus;
use GianTiaga\SpiralCqrs\QueryBusInterface;

final class CqrsBootloaderTest extends TestCase
{
    public function testBootloaderDefinesBusBindings(): void
    {
        $bindings = (new CqrsBootloader())->defineBindings();

        self::assertSame(expected: CommandBus::class, actual: $bindings[CommandBusInterface::class]);
        self::assertSame(expected: QueryBus::class, actual: $bindings[QueryBusInterface::class]);
    }

    public function testBootloaderDoesNotRegisterStatefulCqrsServicesAsSingletons(): void
    {
        $singletons = (new CqrsBootloader())->defineSingletons();

        self::assertArrayNotHasKey(key: CommandBusInterface::class, array: $singletons);
        self::assertArrayNotHasKey(key: QueryBusInterface::class, array: $singletons);
        self::assertArrayNotHasKey(key: 'GianTiaga\\SpiralCqrs\\AfterCommitActions', array: $singletons);
    }

    public function testBootloaderDoesNotHaveFinalizerInitMethod(): void
    {
        self::assertFalse(condition: (new \ReflectionClass(CqrsBootloader::class))->hasMethod(name: 'init'));
    }
}
