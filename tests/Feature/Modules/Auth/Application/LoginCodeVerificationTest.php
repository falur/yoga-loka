<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\ResolveLoginCode\ResolveLoginCodeHandler;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeCommand;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeHandler;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Shared\Domain\Exception\AuthenticationException;
use Tests\Feature\Modules\Auth\Application\AuthApplicationTestCase;

final class LoginCodeVerificationTest extends AuthApplicationTestCase
{
    public function testVerifiesExistingActiveUserAndReturnsTokens(): void
    {
        $this->persistUser('user@example.com', 'user.one');
        $this->persistLoginCode('user@example.com', '123456');

        $verifyResult = $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));

        self::assertFalse($verifyResult->needsProfile);
        self::assertNotNull($verifyResult->tokens);
        self::assertNotSame('', $verifyResult->tokens->accessToken);
        self::assertNull($this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com')));
    }

    public function testVerifiesNewEmailAndIssuesRegistrationTicket(): void
    {
        $this->persistLoginCode('newbie@example.com', '123456');

        $verifyResult = $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'newbie@example.com', code: '123456'));

        self::assertTrue($verifyResult->needsProfile);
        self::assertNull($verifyResult->tokens);
        self::assertNotNull($verifyResult->registrationTicket);

        $ticket = $this->registrationTicketRepository()->findActiveByHashForUpdate(
            SecretHash::fromString($this->secretHasher()->hash($verifyResult->registrationTicket)),
        );
        self::assertInstanceOf(RegistrationTicket::class, $ticket);
        self::assertSame('newbie@example.com', $ticket->email->value());
    }

    public function testWrongCodeIncrementsAttemptsAndThrows(): void
    {
        $this->persistLoginCode('user@example.com', '123456');

        try {
            $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '000000'));
            self::fail('Ожидалось AuthenticationException.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('app.auth.invalid_code', $authenticationException->getMessage());
        }

        $this->cleanOrmHeap();
        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com'));
        self::assertInstanceOf(LoginCode::class, $loginCode);
        self::assertSame(1, $loginCode->attempts->value());
        self::assertFalse($loginCode->isConsumed());
    }

    public function testExhaustedAttemptsThrows(): void
    {
        $this->persistLoginCode('user@example.com', '123456', failedAttempts: 5);

        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));
    }

    public function testExpiredCodeThrows(): void
    {
        $this->persistLoginCode(
            'user@example.com',
            '123456',
            expiration: Expiration::fromDateTime((new \DateTimeImmutable())->sub(new \DateInterval('PT1H'))),
        );

        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));
    }

    public function testMissingCodeThrows(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'absent@example.com', code: '123456'));
    }

    public function testBannedUserIsNotAllowedAndCodeConsumed(): void
    {
        $this->persistUser('banned@example.com', 'banned.user', banned: true);
        $this->persistLoginCode('banned@example.com', '123456');

        try {
            $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'banned@example.com', code: '123456'));
            self::fail('Ожидалось AuthenticationException.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('app.auth.sign_in_not_allowed', $authenticationException->getMessage());
        }

        $this->cleanOrmHeap();
        self::assertNull($this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('banned@example.com')));
    }

    private function verifyHandler(): VerifyLoginCodeHandler
    {
        return new VerifyLoginCodeHandler(
            commandBus: $this->commandBus(),
            resolveLoginCodeHandler: new ResolveLoginCodeHandler(
                loginCodeRepository: $this->loginCodeRepository(),
                secretHasher: $this->secretHasher(),
                tokenGenerator: new RandomTokenGenerator(),
                authTokenStorage: $this->tokenStorage(),
                queryBus: $this->queryBus(),
                findUserForAuthHandler: $this->findUserForAuthHandler(),
                entityManager: $this->entityManager(),
            ),
        );
    }
}
