<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\UnknownIp;
use App\Modules\Auth\Domain\ValueObject\UnknownUserAgent;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ExpirationTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\IpTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\UserAgentTypecast;
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

    public function testIpTypecast(): void
    {
        self::assertInstanceOf(UnknownIp::class, IpTypecast::castDatabaseValue(null));

        $knownIp = IpTypecast::castDatabaseValue('203.0.113.7');

        self::assertInstanceOf(KnownIp::class, $knownIp);
        self::assertSame('203.0.113.7', $knownIp->toNullableString());
        self::assertNull(IpTypecast::uncastValue(new UnknownIp()));
        self::assertSame('203.0.113.7', IpTypecast::uncastValue(KnownIp::fromString('203.0.113.7')));
    }

    public function testUserAgentTypecast(): void
    {
        self::assertInstanceOf(UnknownUserAgent::class, UserAgentTypecast::castDatabaseValue(null));

        $knownUserAgent = UserAgentTypecast::castDatabaseValue('Browser/1');

        self::assertInstanceOf(KnownUserAgent::class, $knownUserAgent);
        self::assertSame('Browser/1', $knownUserAgent->toNullableString());
        self::assertNull(UserAgentTypecast::uncastValue(new UnknownUserAgent()));
        self::assertSame('Browser/1', UserAgentTypecast::uncastValue(KnownUserAgent::fromString('Browser/1')));
    }
}
