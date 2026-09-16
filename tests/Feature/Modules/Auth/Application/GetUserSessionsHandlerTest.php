<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsHandler;
use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsQuery;
use App\Modules\Auth\Application\Result\SessionResult;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\AuthTokenMapper;
use App\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Collection;
use Psr\Log\NullLogger;

final class GetUserSessionsHandlerTest extends AuthApplicationTestCase
{
    public function testReturnsOneSessionPerTokenPairWithDeviceAndRefreshExpiry(): void
    {
        $userId = UserId::generate();
        $this->tokenStorage()->issuePair(userId: $userId, device: SessionDevice::fromRequest(ip: '203.0.113.1', userAgent: 'Agent/A'));
        $this->tokenStorage()->issuePair(userId: $userId, device: SessionDevice::fromRequest(ip: '203.0.113.2', userAgent: 'Agent/B'));
        $this->tokenStorage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
        $this->cleanOrmHeap();

        $sessions = $this->handler()->handle(new GetUserSessionsQuery(
            userId: $userId->value(),
            currentSessionId: SessionId::generate()->value(),
        ));

        self::assertCount(2, $sessions);

        $ips = (new Collection($sessions->all()))
            ->map(static fn(SessionResult $session): string|null => $session->ip)
            ->all();
        self::assertEqualsCanonicalizing(['203.0.113.1', '203.0.113.2'], $ips);

        $thirtyDaysAhead = new \DateTimeImmutable('+30 days');
        foreach ($sessions as $session) {
            // expiresAt берётся от refresh-токена (~60 дней), а не от access (1 час).
            self::assertGreaterThan($session->createdAt, $session->expiresAt);
            self::assertGreaterThan($thirtyDaysAhead, $session->expiresAt);
        }
    }

    public function testReturnsEmptyCollectionForUserWithoutSessions(): void
    {
        $sessions = $this->handler()->handle(new GetUserSessionsQuery(
            userId: UserId::generate()->value(),
            currentSessionId: SessionId::generate()->value(),
        ));

        self::assertTrue($sessions->isEmpty());
    }

    public function testIncludesSessionWithExpiredAccessButLiveRefresh(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $now = new \DateTimeImmutable();
        $device = SessionDevice::fromRequest(ip: '203.0.113.5', userAgent: 'Agent/Live');

        $expiredAccess = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Access,
            tokenHash: TokenHash::fromRawToken('expired-access'),
            expiration: Expiration::fromDateTime($now->sub(new \DateInterval('PT1H'))),
            device: $device,
            now: $now->sub(new \DateInterval('PT2H')),
        );
        $liveRefresh = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('live-refresh'),
            expiration: Expiration::after($now, 5_184_000),
            device: $device,
            now: $now->sub(new \DateInterval('PT2H')),
        );
        $authTokenMapper = new AuthTokenMapper();
        $this->entityManager()->persist($authTokenMapper->toCycleEntity($expiredAccess));
        $this->entityManager()->persist($authTokenMapper->toCycleEntity($liveRefresh));
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $sessions = $this->handler()->handle(new GetUserSessionsQuery(
            userId: $userId->value(),
            currentSessionId: $sessionId->value(),
        ));

        self::assertCount(1, $sessions);
        $session = $sessions->first();
        self::assertInstanceOf(SessionResult::class, $session);
        self::assertSame($sessionId->value(), $session->id);
        self::assertSame('203.0.113.5', $session->ip);
        self::assertTrue($session->current);
    }

    private function handler(): GetUserSessionsHandler
    {
        return new GetUserSessionsHandler(
            authTokenRepository: $this->authTokenRepository(),
            logger: new NullLogger(),
        );
    }
}
