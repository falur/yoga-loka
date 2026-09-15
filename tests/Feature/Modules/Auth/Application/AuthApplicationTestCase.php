<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Spiral\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;
use Tests\Feature\Modules\Auth\Application\Fixture\FakeSecretHasher;

abstract class AuthApplicationTestCase extends DatabaseTestCase
{
    protected function secretHasher(): FakeSecretHasher
    {
        return new FakeSecretHasher();
    }

    protected function tokenStorage(): CycleTokenStorage
    {
        return new CycleTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: new RandomTokenGenerator(),
            entityManager: $this->entityManager(),
        );
    }

    protected function createUserHandler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            entityManager: $this->entityManager(),
            logger: new \Psr\Log\NullLogger(),
            localeResolver: $this->getContainer()->get(\App\Shared\Domain\Locale\LocaleResolver::class),
        );
    }

    protected function findUserForAuthHandler(): FindUserForAuthHandler
    {
        return new FindUserForAuthHandler(userRepository: $this->userRepository());
    }

    protected function persistUser(string $email, string $nickname, bool $banned = false): User
    {
        $user = User::create(
            name: UserName::fromString('Тест Пользователь'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
        $user->confirmEmail();

        if ($banned) {
            $user->ban();
        }

        $this->entityManager()->persist($user);
        $this->entityManager()->run();

        return $user;
    }

    protected function persistLoginCode(
        string $email,
        string $code,
        Expiration|null $expiration = null,
        int $failedAttempts = 0,
        \DateTimeImmutable|null $createdAt = null,
    ): LoginCode {
        $now = $createdAt ?? new \DateTimeImmutable();
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString($email),
            codeHash: SecretHash::fromString($this->secretHasher()->hash($code)),
            expiration: $expiration ?? Expiration::after($now, 600),
            now: $now,
        );

        for ($attempt = 0; $attempt < $failedAttempts; $attempt++) {
            $loginCode->registerFailedAttempt($now);
        }

        $this->entityManager()->persist($loginCode);
        $this->entityManager()->run();

        return $loginCode;
    }

    protected function persistRegistrationTicket(
        string $email,
        string $ticketRaw,
        Expiration|null $expiration = null,
    ): RegistrationTicket {
        $now = new \DateTimeImmutable();
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString($email),
            ticketHash: SecretHash::fromString($this->secretHasher()->hash($ticketRaw)),
            expiration: $expiration ?? Expiration::after($now, 900),
            now: $now,
        );

        $this->entityManager()->persist($ticket);
        $this->entityManager()->run();

        return $ticket;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function commandBus(): CommandBusInterface
    {
        return $this->getContainer()->get(CommandBusInterface::class);
    }

    protected function queryBus(): QueryBusInterface
    {
        return $this->getContainer()->get(QueryBusInterface::class);
    }

    protected function loginCodeRepository(): LoginCodeRepository
    {
        return $this->getContainer()->get(LoginCodeRepository::class);
    }

    protected function registrationTicketRepository(): RegistrationTicketRepository
    {
        return $this->getContainer()->get(RegistrationTicketRepository::class);
    }

    protected function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }

    protected function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    protected function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}
