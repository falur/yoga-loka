<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Application\Query\GetUserSessions;

use App\Modules\Auth\Application\Query\GetUserSessions\AuthSession;
use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuthSessionTest extends TestCase
{
    public function testFromTokensTakesEarliestCreatedLatestExpiryAndSharedDevice(): void
    {
        $sessionId = SessionId::generate();
        $userId = UserId::generate();
        $earliestCreatedAt = new \DateTimeImmutable('2026-06-15 12:00:00');
        $laterCreatedAt = new \DateTimeImmutable('2026-06-15 12:00:05');
        $device = SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/1');

        $accessToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Access,
            tokenHash: TokenHash::fromRawToken('access-raw'),
            expiration: Expiration::after($earliestCreatedAt, 3600),
            device: $device,
            now: $earliestCreatedAt,
        );
        $refreshToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('refresh-raw'),
            expiration: Expiration::after($laterCreatedAt, 5_184_000),
            device: $device,
            now: $laterCreatedAt,
        );

        $session = AuthSession::fromTokens(new AuthTokenCollection([$refreshToken, $accessToken]));

        self::assertTrue($session->sessionId->equals($sessionId));
        self::assertEquals($earliestCreatedAt, $session->createdAt);
        self::assertEquals($refreshToken->expiration->value(), $session->expiresAt->value());
        self::assertInstanceOf(KnownIp::class, $session->ip);
        self::assertSame('203.0.113.7', $session->ip->toNullableString());
        self::assertInstanceOf(KnownUserAgent::class, $session->userAgent);
        self::assertSame('Browser/1', $session->userAgent->toNullableString());
    }

    public function testFromTokensRejectsEmptyCollection(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        AuthSession::fromTokens(new AuthTokenCollection());
    }
}
