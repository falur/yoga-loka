<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit\Domain\Entity;

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
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuthEntityTest extends TestCase
{
    public function testLoginCodeIssueAndLifecycle(): void
    {
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString('user@example.com'),
            codeHash: SecretHash::fromString('code-hash'),
            expiration: Expiration::after($now, 600),
            now: $now,
        );

        self::assertSame('user@example.com', $loginCode->email->value());
        self::assertSame('code-hash', $loginCode->codeHash->value());
        self::assertSame(0, $loginCode->attempts->value());
        self::assertFalse($loginCode->isConsumed());
        self::assertFalse($loginCode->attemptsExhausted());
        self::assertSame($now, $loginCode->createdAt);
        self::assertSame($now, $loginCode->updatedAt);

        self::assertFalse($loginCode->isExpired($now));
        self::assertTrue($loginCode->isExpired($now->add(new \DateInterval('PT601S'))));

        $failedAt = $now->add(new \DateInterval('PT5S'));
        $loginCode->registerFailedAttempt($failedAt);

        self::assertSame(1, $loginCode->attempts->value());
        self::assertSame($failedAt, $loginCode->updatedAt);

        $consumedAt = $now->add(new \DateInterval('PT10S'));
        $loginCode->consume($consumedAt);

        self::assertTrue($loginCode->isConsumed());
        self::assertSame($consumedAt, $loginCode->consumption->value());
    }

    public function testLoginCodeAttemptsExhaustAfterLimit(): void
    {
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString('user@example.com'),
            codeHash: SecretHash::fromString('code-hash'),
            expiration: Expiration::after($now, 600),
            now: $now,
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertFalse($loginCode->attemptsExhausted());
            $loginCode->registerFailedAttempt($now);
        }

        self::assertTrue($loginCode->attemptsExhausted());
        self::assertSame(5, $loginCode->attempts->value());
    }

    public function testRegistrationTicketIssueAndLifecycle(): void
    {
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $email = EmailAddress::fromString('newcomer@example.com');
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: $email,
            ticketHash: SecretHash::fromString('ticket-hash'),
            expiration: Expiration::after($now, 900),
            now: $now,
        );

        self::assertTrue($ticket->email->equals($email));
        self::assertSame('ticket-hash', $ticket->ticketHash->value());
        self::assertFalse($ticket->isConsumed());
        self::assertFalse($ticket->isExpired($now));
        self::assertTrue($ticket->isExpired($now->add(new \DateInterval('PT901S'))));

        $ticket->consume($now->add(new \DateInterval('PT10S')));

        self::assertTrue($ticket->isConsumed());
    }

    public function testAuthTokenIssueAndPredicates(): void
    {
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $sessionId = SessionId::generate();
        $userId = UserId::generate();

        $accessToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Access,
            tokenHash: TokenHash::fromRawToken('access-raw'),
            expiration: Expiration::after($now, 3600),
            device: SessionDevice::fromRequest(ip: '192.0.2.5', userAgent: 'Browser/9'),
            now: $now,
        );
        $refreshToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: AuthTokenType::Refresh,
            tokenHash: TokenHash::fromRawToken('refresh-raw'),
            expiration: Expiration::after($now, 5_184_000),
            device: SessionDevice::unknown(),
            now: $now,
        );

        self::assertTrue($accessToken->isAccess());
        self::assertFalse($accessToken->isRefresh());
        self::assertTrue($refreshToken->isRefresh());
        self::assertFalse($refreshToken->isAccess());
        self::assertTrue($accessToken->sessionId->equals($refreshToken->sessionId));
        self::assertFalse($accessToken->isExpired($now));
        self::assertTrue($accessToken->isExpired($now->add(new \DateInterval('PT3601S'))));

        self::assertInstanceOf(KnownIp::class, $accessToken->ip);
        self::assertSame('192.0.2.5', $accessToken->ip->toNullableString());
        self::assertInstanceOf(KnownUserAgent::class, $accessToken->userAgent);
        self::assertSame('Browser/9', $accessToken->userAgent->toNullableString());
        self::assertInstanceOf(UnknownIp::class, $refreshToken->ip);
        self::assertInstanceOf(UnknownUserAgent::class, $refreshToken->userAgent);
    }
}
