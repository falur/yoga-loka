<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Persistence\Cycle;

use App\Shared\Infrastructure\Persistence\Cycle\LazyGhostReflectionRegistry;
use PHPUnit\Framework\TestCase;

final class LazyGhostReflectionRegistryTest extends TestCase
{
    public function testPropertyWalksUpToParentClass(): void
    {
        $registry = new LazyGhostReflectionRegistry();
        $childReflection = $registry->reflection(StubReflectionChild::class);

        $privateInParent = $registry->property($childReflection, 'privateInParent');

        self::assertNotNull($privateInParent);
        self::assertSame('privateInParent', $privateInParent->getName());
    }

    public function testPropertyReturnsNullWhenMissingAndNoParent(): void
    {
        $registry = new LazyGhostReflectionRegistry();
        $rootReflection = $registry->reflection(StubReflectionParent::class);

        self::assertNull($registry->property($rootReflection, 'doesNotExist'));
    }

    public function testReflectionIsCachedPerClass(): void
    {
        $registry = new LazyGhostReflectionRegistry();

        self::assertSame(
            $registry->reflection(StubReflectionChild::class),
            $registry->reflection(StubReflectionChild::class),
        );
    }
}

class StubReflectionParent
{
    private int $privateInParent = 0;
}

final class StubReflectionChild extends StubReflectionParent
{
    public int $childProperty = 0;
}
