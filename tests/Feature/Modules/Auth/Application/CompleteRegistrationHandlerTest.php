<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationCommand;
use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationHandler;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\Auth\Domain\Exception\InvalidRegistrationTicketException;
use App\Modules\User\Domain\Exception\NicknameAlreadyTakenException;
use Psr\Log\NullLogger;

final class CompleteRegistrationHandlerTest extends AuthApplicationTestCase
{
    public function testCompletesRegistrationCreatesActiveUserAndTokens(): void
    {
        $this->persistRegistrationTicket('newbie@example.com', 'ticket-raw-1');

        $tokens = $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'ticket-raw-1',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));

        self::assertNotSame('', $tokens->accessToken);
        self::assertNotSame('', $tokens->refreshToken);

        $user = $this->userRepository()->findByEmail(Email::fromString('newbie@example.com'));
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActive());

        self::assertNull(
            $this->registrationTicketRepository()->findActiveByHashForUpdate(
                SecretHash::fromString($this->secretHasher()->hash('ticket-raw-1')),
            ),
        );
    }

    public function testRejectsUnknownTicket(): void
    {
        $this->expectException(InvalidRegistrationTicketException::class);

        $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'unknown-ticket',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));
    }

    public function testRejectsExpiredTicket(): void
    {
        $this->persistRegistrationTicket(
            'newbie@example.com',
            'ticket-raw-2',
            expiration: Expiration::fromDateTime((new \DateTimeImmutable())->sub(new \DateInterval('PT1H'))),
        );

        $this->expectException(InvalidRegistrationTicketException::class);

        $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'ticket-raw-2',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));
    }

    public function testRejectsTakenNicknameAndKeepsTicketReusable(): void
    {
        $this->persistUser('owner@example.com', 'taken.nick');
        $this->persistRegistrationTicket('newbie@example.com', 'ticket-raw-3');

        try {
            $this->handler()->handle(new CompleteRegistrationCommand(
                ticket: 'ticket-raw-3',
                name: 'Имя Тест',
                nickname: 'taken.nick',
                requestLocale: 'ru',
            ));
            self::fail('Ожидалось NicknameAlreadyTakenException.');
        } catch (NicknameAlreadyTakenException $validationException) {
            self::assertSame('app.user.nickname_taken', $validationException->getMessage());
        }

        $this->cleanOrmHeap();
        $ticket = $this->registrationTicketRepository()->findActiveByHashForUpdate(
            SecretHash::fromString($this->secretHasher()->hash('ticket-raw-3')),
        );
        self::assertInstanceOf(RegistrationTicket::class, $ticket);
    }

    private function handler(): CompleteRegistrationHandler
    {
        return new CompleteRegistrationHandler(
            registrationTicketRepository: $this->registrationTicketRepository(),
            secretHasher: $this->secretHasher(),
            authTokenStorage: $this->tokenStorage(),
            users: $this->users(),
            logger: new NullLogger(),
        );
    }
}
