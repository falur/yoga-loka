<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Application\View;

use App\Modules\Auth\Application\View\SessionView;
use App\Modules\Auth\Application\View\SessionViewAssembler;
use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class SessionViewAssemblerTest extends TestCase
{
    public function testFromSessionTokensTakesEarliestCreatedLatestExpiryAndSharedDevice(): void
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

        $session = (new SessionViewAssembler())->fromSessionTokens(
            sessionTokens: new AuthTokenCollection([$refreshToken, $accessToken]),
            currentSessionId: SessionId::generate()->value(),
        );

        self::assertSame($sessionId->value(), $session->id);
        self::assertEquals($earliestCreatedAt, $session->createdAt);
        self::assertEquals($refreshToken->expiration->value(), $session->expiresAt);
        self::assertSame('203.0.113.7', $session->ip);
        self::assertSame('Browser/1', $session->device);
        self::assertFalse($session->current);
    }

    public function testFromSessionTokensMarksCurrentSessionWhenIdMatches(): void
    {
        $sessionId = SessionId::generate();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $token = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: UserId::generate(),
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('refresh-raw'),
            expiration: Expiration::after($now, 5_184_000),
            device: SessionDevice::unknown(),
            now: $now,
        );

        $session = (new SessionViewAssembler())->fromSessionTokens(
            sessionTokens: new AuthTokenCollection([$token]),
            currentSessionId: $sessionId->value(),
        );

        self::assertTrue($session->current);
        self::assertNull($session->ip);
        self::assertNull($session->device);
    }

    public function testFromActiveTokensGroupsTokensBySession(): void
    {
        $userId = UserId::generate();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $currentSessionId = SessionId::generate();
        $otherSessionId = SessionId::generate();

        $tokens = new AuthTokenCollection([
            $this->refreshToken(userId: $userId, sessionId: $currentSessionId, ip: '203.0.113.1', now: $now),
            $this->refreshToken(userId: $userId, sessionId: $otherSessionId, ip: '203.0.113.2', now: $now),
        ]);

        $sessions = (new SessionViewAssembler())->fromActiveTokens(
            activeTokens: $tokens,
            currentSessionId: $currentSessionId->value(),
        );

        self::assertCount(2, $sessions);
        $current = $sessions->toBase()->first(static fn(SessionView $session): bool => $session->current);
        self::assertInstanceOf(SessionView::class, $current);
        self::assertSame($currentSessionId->value(), $current->id);
        self::assertSame('203.0.113.1', $current->ip);
    }

    public function testFromSessionTokensRejectsEmptyCollection(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        (new SessionViewAssembler())->fromSessionTokens(
            sessionTokens: new AuthTokenCollection(),
            currentSessionId: SessionId::generate()->value(),
        );
    }

    private function refreshToken(UserId $userId, SessionId $sessionId, string $ip, \DateTimeImmutable $now): AuthToken
    {
        return AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('refresh-' . $ip),
            expiration: Expiration::after($now, 5_184_000),
            device: SessionDevice::fromRequest(ip: $ip, userAgent: 'Agent/' . $ip),
            now: $now,
        );
    }
}
