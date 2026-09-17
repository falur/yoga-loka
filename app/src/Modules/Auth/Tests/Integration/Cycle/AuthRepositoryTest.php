<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Cycle;

use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Domain\ValueObject\UnknownIp;
use App\Modules\Auth\Domain\ValueObject\UnknownUserAgent;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\Repository\RegistrationTicketRepository;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\AuthTokenMapper;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\LoginCodeMapper;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\RegistrationTicketMapper;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class AuthRepositoryTest extends DatabaseTestCase
{
    private \DateTimeImmutable $now;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable('2026-06-15 12:00:00');
    }

    public function testFindsLatestActiveLoginCodeWithAndWithoutLock(): void
    {
        $email = EmailAddress::fromString('codes@example.com');
        $first = $this->loginCode($email, 'hash-1');
        $second = $this->loginCode($email, 'hash-2');
        $this->persist($first, $second);
        $this->cleanOrmHeap();

        $expectedId = \strcmp($first->id->value(), $second->id->value()) > 0 ? $first->id : $second->id;

        $active = $this->loginCodeRepository()->findActiveByEmail($email);
        $lockedActive = $this->loginCodeRepository()->findActiveByEmailForUpdate($email);

        self::assertInstanceOf(LoginCode::class, $active);
        self::assertInstanceOf(LoginCode::class, $lockedActive);
        self::assertTrue($expectedId->equals($active->id));
        self::assertTrue($expectedId->equals($lockedActive->id));
    }

    public function testIgnoresConsumedLoginCode(): void
    {
        $email = EmailAddress::fromString('consumed@example.com');
        $loginCode = $this->loginCode($email, 'hash-consumed');
        $loginCode->consume($this->now);
        $this->persist($loginCode);
        $this->cleanOrmHeap();

        self::assertNull($this->loginCodeRepository()->findActiveByEmail($email));
        self::assertNull($this->loginCodeRepository()->findActiveByEmailForUpdate($email));
    }

    public function testReturnsNullForUnknownLoginCodeEmail(): void
    {
        self::assertNull(
            $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('unknown@example.com')),
        );
    }

    public function testHydratesLoginCodeWithAttempts(): void
    {
        $email = EmailAddress::fromString('hydrate@example.com');
        $loginCode = $this->loginCode($email, 'hash-hydrate');
        $loginCode->registerFailedAttempt($this->now);
        $loginCode->registerFailedAttempt($this->now);
        $this->persist($loginCode);
        $this->cleanOrmHeap();

        $restored = $this->loginCodeRepository()->findActiveByEmail($email);

        self::assertInstanceOf(LoginCode::class, $restored);
        self::assertSame(2, $restored->attempts->value());
        self::assertSame('hash-hydrate', $restored->codeHash->value());
        self::assertFalse($restored->isConsumed());
        self::assertSame(
            $loginCode->expiration->value()->format('Y-m-d H:i:s'),
            $restored->expiration->value()->format('Y-m-d H:i:s'),
        );
    }

    public function testFindsActiveRegistrationTicketByHash(): void
    {
        $email = EmailAddress::fromString('ticket@example.com');
        $ticket = $this->registrationTicket($email, 'ticket-hash');
        $this->persist($ticket);
        $this->cleanOrmHeap();

        $restored = $this->registrationTicketRepository()
            ->findActiveByHashForUpdate(SecretHash::fromString('ticket-hash'));

        self::assertInstanceOf(RegistrationTicket::class, $restored);
        self::assertSame('ticket@example.com', $restored->email->value());
    }

    public function testIgnoresConsumedRegistrationTicket(): void
    {
        $ticket = $this->registrationTicket(EmailAddress::fromString('used-ticket@example.com'), 'used-hash');
        $ticket->consume($this->now);
        $this->persist($ticket);
        $this->cleanOrmHeap();

        self::assertNull(
            $this->registrationTicketRepository()->findActiveByHashForUpdate(SecretHash::fromString('used-hash')),
        );
    }

    public function testReturnsNullForUnknownTicketHash(): void
    {
        self::assertNull(
            $this->registrationTicketRepository()->findActiveByHashForUpdate(SecretHash::fromString('absent-hash')),
        );
    }

    public function testFindsAndHydratesAuthTokensBySession(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $accessToken = $this->authToken(
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Access,
            rawToken: 'access-raw',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );
        $refreshToken = $this->authToken(
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            rawToken: 'refresh-raw',
            ttlSeconds: 5_184_000,
            device: SessionDevice::unknown(),
        );
        $this->persist($accessToken, $refreshToken);
        $this->cleanOrmHeap();

        $restoredAccess = $this->authTokenRepository()->findByHash(TokenHash::fromRawToken('access-raw'));
        $restoredRefresh = $this->authTokenRepository()->findByHashForUpdate(TokenHash::fromRawToken('refresh-raw'));
        $sessionTokens = $this->authTokenRepository()->findBySessionIdForUpdate($sessionId);

        self::assertInstanceOf(AuthToken::class, $restoredAccess);
        self::assertSame(AuthTokenType::Access, $restoredAccess->type);
        self::assertTrue($restoredAccess->userId->equals($userId));
        self::assertTrue($restoredAccess->sessionId->equals($sessionId));
        self::assertInstanceOf(AuthToken::class, $restoredRefresh);
        self::assertSame(AuthTokenType::Refresh, $restoredRefresh->type);
        self::assertCount(2, $sessionTokens);
    }

    public function testHydratesAuthTokenDeviceForKnownAndUnknownValues(): void
    {
        $userId = UserId::generate();
        $knownDeviceToken = $this->authToken(
            userId: $userId,
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            rawToken: 'known-device',
            ttlSeconds: 3600,
            device: SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Mozilla/5.0 Test'),
        );
        $unknownDeviceToken = $this->authToken(
            userId: $userId,
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            rawToken: 'unknown-device',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );
        $this->persist($knownDeviceToken, $unknownDeviceToken);
        $this->cleanOrmHeap();

        $restoredKnown = $this->authTokenRepository()->findByHash(TokenHash::fromRawToken('known-device'));
        $restoredUnknown = $this->authTokenRepository()->findByHash(TokenHash::fromRawToken('unknown-device'));

        self::assertInstanceOf(AuthToken::class, $restoredKnown);
        self::assertInstanceOf(KnownIp::class, $restoredKnown->ip);
        self::assertSame('203.0.113.7', $restoredKnown->ip->toNullableString());
        self::assertInstanceOf(KnownUserAgent::class, $restoredKnown->userAgent);
        self::assertSame('Mozilla/5.0 Test', $restoredKnown->userAgent->toNullableString());

        self::assertInstanceOf(AuthToken::class, $restoredUnknown);
        self::assertInstanceOf(UnknownIp::class, $restoredUnknown->ip);
        self::assertNull($restoredUnknown->ip->toNullableString());
        self::assertInstanceOf(UnknownUserAgent::class, $restoredUnknown->userAgent);
        self::assertNull($restoredUnknown->userAgent->toNullableString());
    }

    public function testFindActiveByUserIdReturnsOnlyOwnActiveTokensNewestSessionFirst(): void
    {
        $userId = UserId::generate();
        $sessionA = SessionId::generate();
        $sessionB = SessionId::generate();
        $ownTokenA = $this->authToken(
            userId: $userId,
            sessionId: $sessionA,
            type: AuthTokenType::Access,
            rawToken: 'active-a',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );
        $ownTokenB = $this->authToken(
            userId: $userId,
            sessionId: $sessionB,
            type: AuthTokenType::Refresh,
            rawToken: 'active-b',
            ttlSeconds: 5_184_000,
            device: SessionDevice::unknown(),
        );
        $foreignToken = $this->authToken(
            userId: UserId::generate(),
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            rawToken: 'foreign',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );
        $expiredToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: SessionId::generate(),
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('expired'),
            expiration: Expiration::fromDateTime($this->now->sub(new \DateInterval('PT1H'))),
            device: SessionDevice::unknown(),
            now: $this->now->sub(new \DateInterval('P1D')),
        );
        $this->persist($ownTokenA, $ownTokenB, $foreignToken, $expiredToken);
        $this->cleanOrmHeap();

        $activeTokens = $this->authTokenRepository()->findActiveByUserId($userId, $this->now);

        self::assertCount(2, $activeTokens);
        $firstToken = $activeTokens->first();
        self::assertInstanceOf(AuthToken::class, $firstToken);
        $expectedNewestSession = \strcmp($sessionA->value(), $sessionB->value()) > 0 ? $sessionA : $sessionB;
        self::assertTrue($expectedNewestSession->equals($firstToken->sessionId));
        foreach ($activeTokens as $activeToken) {
            self::assertTrue($activeToken->userId->equals($userId));
        }
    }

    public function testFindByUserAndSessionForUpdateReturnsOnlyOwnSessionTokens(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $accessToken = $this->authToken(
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Access,
            rawToken: 'own-access',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );
        $refreshToken = $this->authToken(
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            rawToken: 'own-refresh',
            ttlSeconds: 5_184_000,
            device: SessionDevice::unknown(),
        );
        $this->persist($accessToken, $refreshToken);
        $this->cleanOrmHeap();

        $ownTokens = $this->authTokenRepository()->findByUserAndSessionForUpdate($userId, $sessionId);
        $foreignTokens = $this->authTokenRepository()
            ->findByUserAndSessionForUpdate(UserId::generate(), $sessionId);

        self::assertCount(2, $ownTokens);
        self::assertTrue($foreignTokens->isEmpty());
    }

    public function testReturnsNullForUnknownTokenHash(): void
    {
        self::assertNull($this->authTokenRepository()->findByHash(TokenHash::fromRawToken('missing')));
    }

    /**
     * Токен мог быть удалён параллельным отзывом сессии между чтением и удалением: домен больше
     * не несёт разметку Cycle, поэтому репозиторий сам ищет строку перед удалением и молча
     * выходит, если её уже нет, — повторный отзыв не должен падать.
     */
    public function testDeleteIgnoresTokenMissingInDatabase(): void
    {
        $missingToken = $this->authToken(
            userId: UserId::generate(),
            sessionId: SessionId::generate(),
            type: AuthTokenType::Access,
            rawToken: 'never-stored',
            ttlSeconds: 3600,
            device: SessionDevice::unknown(),
        );

        $this->authTokenRepository()->delete($missingToken);

        self::assertNull($this->authTokenRepository()->findByHash(TokenHash::fromRawToken('never-stored')));
    }

    public function testRejectsDuplicateTokenHash(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $authTokenMapper = new AuthTokenMapper();
        $this->entityManager()->persist(
            $authTokenMapper->toCycleEntity($this->authToken(
                userId: $userId,
                sessionId: $sessionId,
                type: AuthTokenType::Access,
                rawToken: 'dup',
                ttlSeconds: 3600,
                device: SessionDevice::unknown(),
            )),
        );
        $this->entityManager()->persist(
            $authTokenMapper->toCycleEntity($this->authToken(
                userId: $userId,
                sessionId: $sessionId,
                type: AuthTokenType::Refresh,
                rawToken: 'dup',
                ttlSeconds: 3600,
                device: SessionDevice::unknown(),
            )),
        );

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function loginCode(EmailAddress $email, string $hash): LoginCode
    {
        return LoginCode::issue(
            id: LoginCodeId::generate(),
            email: $email,
            codeHash: SecretHash::fromString($hash),
            expiration: Expiration::after($this->now, 600),
            now: $this->now,
        );
    }

    private function registrationTicket(EmailAddress $email, string $hash): RegistrationTicket
    {
        return RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: $email,
            ticketHash: SecretHash::fromString($hash),
            expiration: Expiration::after($this->now, 900),
            now: $this->now,
        );
    }

    private function authToken(
        UserId $userId,
        SessionId $sessionId,
        AuthTokenType $type,
        string $rawToken,
        int $ttlSeconds,
        SessionDevice $device,
    ): AuthToken {
        return AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: $type,
            tokenHash: TokenHash::fromRawToken($rawToken),
            expiration: Expiration::after($this->now, $ttlSeconds),
            device: $device,
            now: $this->now,
        );
    }

    /**
     * LoginCode/RegistrationTicket/AuthToken — чистые доменные сущности без Cycle-разметки,
     * поэтому персист идёт через их Mapper, как это делают одноимённые CycleRepository.
     */
    private function persist(LoginCode|RegistrationTicket|AuthToken ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($this->toCycleEntity($entity));
        }

        $this->entityManager()->run();
    }

    private function toCycleEntity(LoginCode|RegistrationTicket|AuthToken $entity): object
    {
        return match (true) {
            $entity instanceof LoginCode => (new LoginCodeMapper())->toCycleEntity($entity),
            $entity instanceof RegistrationTicket => (new RegistrationTicketMapper())->toCycleEntity($entity),
            $entity instanceof AuthToken => (new AuthTokenMapper())->toCycleEntity($entity),
        };
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function loginCodeRepository(): LoginCodeRepository
    {
        return $this->getContainer()->get(LoginCodeRepository::class);
    }

    private function registrationTicketRepository(): RegistrationTicketRepository
    {
        return $this->getContainer()->get(RegistrationTicketRepository::class);
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }
}
