<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Cycle;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Infrastructure\Cycle\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Cycle\ExpirationTypecast;
use PHPUnit\Framework\TestCase;

final class AuthTypecastTest extends TestCase
{
    public function testExpirationTypecast(): void
    {
        $immutable = new \DateTimeImmutable('2026-06-15 12:00:00');
        $mutable = new \DateTime('2026-06-16 12:00:00');

        self::assertSame($immutable, ExpirationTypecast::castDatabaseValue($immutable)->value());
        self::assertSame(
            $mutable->format('c'),
            ExpirationTypecast::castDatabaseValue($mutable)->value()->format('c'),
        );
        self::assertSame(
            '2026-06-17T12:00:00+00:00',
            ExpirationTypecast::castDatabaseValue('2026-06-17 12:00:00')->value()->format('c'),
        );
        self::assertSame($immutable, ExpirationTypecast::uncastValue(Expiration::fromDateTime($immutable)));
    }

    public function testConsumptionTypecast(): void
    {
        $immutable = new \DateTimeImmutable('2026-06-15 12:00:00');
        $mutable = new \DateTime('2026-06-16 12:00:00');

        self::assertFalse(ConsumptionTypecast::castDatabaseValue(null)->isConsumed());
        self::assertSame($immutable, ConsumptionTypecast::castDatabaseValue($immutable)->value());
        self::assertSame(
            $mutable->format('c'),
            ConsumptionTypecast::castDatabaseValue($mutable)->value()?->format('c'),
        );
        self::assertSame(
            '2026-06-17T12:00:00+00:00',
            ConsumptionTypecast::castDatabaseValue('2026-06-17 12:00:00')->value()?->format('c'),
        );
        self::assertNull(ConsumptionTypecast::uncastValue(Consumption::notConsumed()));
        self::assertSame($immutable, ConsumptionTypecast::uncastValue(Consumption::at($immutable)));
    }
}
