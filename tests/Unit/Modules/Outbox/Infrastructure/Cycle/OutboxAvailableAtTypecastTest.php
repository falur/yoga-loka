<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure\Cycle;

use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Modules\Outbox\Infrastructure\Cycle\OutboxAvailableAtTypecast;
use PHPUnit\Framework\TestCase;

final class OutboxAvailableAtTypecastTest extends TestCase
{
    public function testCastConvertsImmutableAndMutableDates(): void
    {
        $immutable = new \DateTimeImmutable('2026-05-25 15:37:00');
        self::assertSame($immutable, OutboxAvailableAtTypecast::castDatabaseValue($immutable)->value());

        $mutable = new \DateTime('2026-05-25 15:37:00');
        self::assertEquals(
            \DateTimeImmutable::createFromInterface($mutable),
            OutboxAvailableAtTypecast::castDatabaseValue($mutable)->value(),
        );
    }

    public function testUncastReturnsUnderlyingDate(): void
    {
        $immutable = new \DateTimeImmutable('2026-05-25 15:37:00');

        self::assertSame(
            $immutable,
            OutboxAvailableAtTypecast::uncastValue(OutboxAvailableAt::fromDateTime($immutable)),
        );
    }
}
