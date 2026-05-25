<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Bus;

use PHPUnit\Framework\TestCase;
use Tools\Cqrs\Bootloader\CqrsBootloader;
use Tools\Cqrs\CommandBus;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\QueryBus;
use Tools\Cqrs\QueryBusInterface;

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
        self::assertArrayNotHasKey(key: 'Tools\\Cqrs\\AfterCommitActions', array: $singletons);
    }

    public function testBootloaderDoesNotHaveFinalizerInitMethod(): void
    {
        self::assertFalse(condition: (new \ReflectionClass(CqrsBootloader::class))->hasMethod(name: 'init'));
    }
}
