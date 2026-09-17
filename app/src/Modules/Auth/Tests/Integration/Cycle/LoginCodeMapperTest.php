<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Cycle;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\LoginCodeMapper;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённого ConsumptionTypecast (в части LoginCode) на Mapper, куда
 * переехала логика null-bridging между nullable-колонкой consumed_at и null-object Consumption.
 */
final class LoginCodeMapperTest extends TestCase
{
    public function testMapsConsumedCodeToAndFromCycleEntity(): void
    {
        $mapper = new LoginCodeMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $consumedAt = new \DateTimeImmutable('2026-06-15 12:05:00');

        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString('user@example.com'),
            codeHash: SecretHash::fromString('code-hash'),
            expiration: Expiration::after($now, 600),
            now: $now,
        );
        $loginCode->registerFailedAttempt($now);
        $loginCode->consume($consumedAt);

        $cycleEntity = $mapper->toCycleEntity($loginCode);

        self::assertSame($loginCode->id->value(), $cycleEntity->id);
        self::assertSame('user@example.com', $cycleEntity->email);
        self::assertSame('code-hash', $cycleEntity->codeHash);
        self::assertSame(1, $cycleEntity->attempts);
        self::assertSame($consumedAt, $cycleEntity->consumedAt);

        $restoredCode = $mapper->toDomain($cycleEntity);

        self::assertTrue($loginCode->id->equals($restoredCode->id));
        self::assertSame(1, $restoredCode->attempts->value());
        self::assertTrue($restoredCode->isConsumed());
        self::assertSame($consumedAt, $restoredCode->consumption->value());
    }

    public function testMapsNotConsumedCodeToNullColumn(): void
    {
        $mapper = new LoginCodeMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString('fresh@example.com'),
            codeHash: SecretHash::fromString('fresh-hash'),
            expiration: Expiration::after($now, 600),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($loginCode);

        self::assertNull($cycleEntity->consumedAt);
        self::assertSame(0, $cycleEntity->attempts);

        $restoredCode = $mapper->toDomain($cycleEntity);

        self::assertFalse($restoredCode->isConsumed());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new LoginCodeMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString('reuse@example.com'),
            codeHash: SecretHash::fromString('reuse-hash'),
            expiration: Expiration::after($now, 600),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($loginCode);
        $loginCode->registerFailedAttempt($now);
        $updatedCycleEntity = $mapper->toCycleEntity($loginCode, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame(1, $updatedCycleEntity->attempts);
    }
}
