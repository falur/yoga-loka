<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Trait;

use App\Shared\Domain\Trait\HasTimestamps;
use PHPUnit\Framework\TestCase;

final class HasTimestampsTest extends TestCase
{
    public function testInitializesCreatedAtAndUpdatedAt(): void
    {
        $entity = new HasTimestampsProbe();
        $now = new \DateTimeImmutable('2026-05-21 18:41:00');

        $entity->initializeTimestamps($now);

        self::assertSame($now, $entity->createdAt);
        self::assertSame($now, $entity->updatedAt);
    }

    public function testTouchChangesOnlyUpdatedAt(): void
    {
        $entity = new HasTimestampsProbe();
        $createdAt = new \DateTimeImmutable('2026-05-21 18:41:00');
        $updatedAt = new \DateTimeImmutable('2026-05-21 18:42:00');

        $entity->initializeTimestamps($createdAt);
        $entity->touch($updatedAt);

        self::assertSame($createdAt, $entity->createdAt);
        self::assertSame($updatedAt, $entity->updatedAt);
    }
}

final class HasTimestampsProbe
{
    use HasTimestamps;
}
