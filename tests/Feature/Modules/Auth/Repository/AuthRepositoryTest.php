<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
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
        $accessToken = $this->authToken($userId, $sessionId, AuthTokenType::Access, 'access-raw', 3600);
        $refreshToken = $this->authToken($userId, $sessionId, AuthTokenType::Refresh, 'refresh-raw', 5_184_000);
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

    public function testReturnsNullForUnknownTokenHash(): void
    {
        self::assertNull($this->authTokenRepository()->findByHash(TokenHash::fromRawToken('missing')));
    }

    public function testRejectsDuplicateTokenHash(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $this->entityManager()->persist($this->authToken($userId, $sessionId, AuthTokenType::Access, 'dup', 3600));
        $this->entityManager()->persist($this->authToken($userId, $sessionId, AuthTokenType::Refresh, 'dup', 3600));

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
    ): AuthToken {
        return AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: $type,
            tokenHash: TokenHash::fromRawToken($rawToken),
            expiration: Expiration::after($this->now, $ttlSeconds),
            now: $this->now,
        );
    }

    private function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->run();
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
