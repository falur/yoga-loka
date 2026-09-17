<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit\Application\Query\GetUserSessions;

use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsHandler;
use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsQuery;
use App\Modules\Auth\Application\Result\SessionResult;
use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Юнит-проверка сборки SessionResult, растворённой из бывшего SessionViewAssembler в приватные
 * методы GetUserSessionsHandler: группировка токенов по sessionId, earliest createdAt, latest
 * expiresAt, устройство первого токена, пометка текущей сессии. Repository здесь — дублёр без
 * обращения к БД, реальное чтение проверяет Feature-тест этого же handler-а.
 */
final class GetUserSessionsHandlerTest extends TestCase
{
    public function testGroupsTokensBySessionTakingEarliestCreatedLatestExpiryAndSharedDevice(): void
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

        $sessions = $this->handler(new AuthTokenCollection([$refreshToken, $accessToken]))->handle(new GetUserSessionsQuery(
            userId: $userId->value(),
            currentSessionId: SessionId::generate()->value(),
        ));

        self::assertCount(1, $sessions);
        $session = $sessions->first();
        self::assertInstanceOf(SessionResult::class, $session);
        self::assertSame($sessionId->value(), $session->id);
        self::assertEquals($earliestCreatedAt, $session->createdAt);
        self::assertEquals($refreshToken->expiration->value(), $session->expiresAt);
        self::assertSame('203.0.113.7', $session->ip);
        self::assertSame('Browser/1', $session->device);
        self::assertFalse($session->current);
    }

    public function testMarksCurrentSessionWhenIdMatches(): void
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

        $sessions = $this->handler(new AuthTokenCollection([$token]))->handle(new GetUserSessionsQuery(
            userId: UserId::generate()->value(),
            currentSessionId: $sessionId->value(),
        ));

        $session = $sessions->first();
        self::assertInstanceOf(SessionResult::class, $session);
        self::assertTrue($session->current);
        self::assertNull($session->ip);
        self::assertNull($session->device);
    }

    public function testGroupsMultipleSessionsIndependently(): void
    {
        $userId = UserId::generate();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $currentSessionId = SessionId::generate();
        $otherSessionId = SessionId::generate();

        $tokens = new AuthTokenCollection([
            $this->refreshToken(userId: $userId, sessionId: $currentSessionId, ip: '203.0.113.1', now: $now),
            $this->refreshToken(userId: $userId, sessionId: $otherSessionId, ip: '203.0.113.2', now: $now),
        ]);

        $sessions = $this->handler($tokens)->handle(new GetUserSessionsQuery(
            userId: $userId->value(),
            currentSessionId: $currentSessionId->value(),
        ));

        self::assertCount(2, $sessions);
        $current = $sessions->toBase()->first(static fn(SessionResult $session): bool => $session->current);
        self::assertInstanceOf(SessionResult::class, $current);
        self::assertSame($currentSessionId->value(), $current->id);
        self::assertSame('203.0.113.1', $current->ip);
    }

    public function testRejectsEmptySessionTokenGroup(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        // Группа без единого токена недостижима через handle() (groupBy никогда не даёт пустую
        // группу), но приватный метод остаётся защитным инвариантом — вызывается напрямую рефлексией,
        // как и другие защитные проверки внутренних методов в проекте.
        (new \ReflectionMethod(GetUserSessionsHandler::class, 'fromSessionTokens'))->invoke(
            $this->handler(new AuthTokenCollection()),
            new AuthTokenCollection(),
            SessionId::generate()->value(),
        );
    }

    private function handler(AuthTokenCollection $activeTokens): GetUserSessionsHandler
    {
        $authTokenRepository = $this->createStub(AuthTokenRepository::class);
        $authTokenRepository->method('findActiveByUserId')->willReturn($activeTokens);

        return new GetUserSessionsHandler(
            authTokenRepository: $authTokenRepository,
            logger: new NullLogger(),
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
