<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Domain\ValueObject\UnknownIp;
use App\Modules\Auth\Domain\ValueObject\UnknownUserAgent;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\AuthTokenMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённых typecast-классов AuthToken (ExpirationTypecast, IpTypecast,
 * UserAgentTypecast) на Mapper, куда переехала их логика null-bridging между nullable-колонкой
 * и value object с сентинелом (ip/user_agent) и восстановления Expiration из NOT NULL-колонки.
 */
final class AuthTokenMapperTest extends TestCase
{
    public function testMapsKnownDeviceToAndFromCycleEntity(): void
    {
        $mapper = new AuthTokenMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $expiresAt = new \DateTimeImmutable('2026-06-15 13:00:00');

        $authToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: UserId::generate(),
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            tokenHash: TokenHash::fromRawToken('raw-token'),
            expiration: Expiration::fromDateTime($expiresAt),
            device: SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/1'),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($authToken);

        self::assertSame($authToken->id->value(), $cycleEntity->id);
        self::assertSame(AuthTokenType::Access, $cycleEntity->type);
        self::assertSame($expiresAt, $cycleEntity->expiresAt);
        self::assertSame('203.0.113.7', $cycleEntity->ip);
        self::assertSame('Browser/1', $cycleEntity->userAgent);

        $restoredToken = $mapper->toDomain($cycleEntity);

        self::assertTrue($authToken->id->equals($restoredToken->id));
        self::assertSame($expiresAt, $restoredToken->expiration->value());
        self::assertInstanceOf(KnownIp::class, $restoredToken->ip);
        self::assertSame('203.0.113.7', $restoredToken->ip->toNullableString());
        self::assertInstanceOf(KnownUserAgent::class, $restoredToken->userAgent);
        self::assertSame('Browser/1', $restoredToken->userAgent->toNullableString());
    }

    public function testMapsUnknownDeviceToNullColumns(): void
    {
        $mapper = new AuthTokenMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');

        $authToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: UserId::generate(),
            sessionId: SessionId::generate(),
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('raw-refresh'),
            expiration: Expiration::after($now, 3600),
            device: SessionDevice::unknown(),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($authToken);

        self::assertNull($cycleEntity->ip);
        self::assertNull($cycleEntity->userAgent);

        $restoredToken = $mapper->toDomain($cycleEntity);

        self::assertInstanceOf(UnknownIp::class, $restoredToken->ip);
        self::assertNull($restoredToken->ip->toNullableString());
        self::assertInstanceOf(UnknownUserAgent::class, $restoredToken->userAgent);
        self::assertNull($restoredToken->userAgent->toNullableString());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new AuthTokenMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $authToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: UserId::generate(),
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            tokenHash: TokenHash::fromRawToken('raw-reuse'),
            expiration: Expiration::after($now, 3600),
            device: SessionDevice::unknown(),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($authToken);
        $updatedCycleEntity = $mapper->toCycleEntity($authToken, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
    }
}
