<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\LoginCodeMapper;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\RegistrationTicketMapper;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenIssuer;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenIssuing;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Auth\SpiralTokenStorage;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\Repository\RegistrationTicketRepository;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Modules\User\Infrastructure\Spiral\PublicApi\UserProvider;
use App\Modules\User\Public\Contract\UserContract;
use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;

abstract class AuthApplicationTestCase extends DatabaseTestCase
{
    protected function secretHasher(): FakeSecretHasher
    {
        return new FakeSecretHasher();
    }

    /**
     * Доменная граница AuthTokenStorageContract (issuePair/rotate/revokeSession) — тот же
     * биндинг, что и в AuthBootloader.
     */
    protected function tokenStorage(): AuthTokenIssuer
    {
        return new AuthTokenIssuer(
            authTokenRepository: $this->authTokenRepository(),
            authTokenIssuing: $this->authTokenIssuing(),
        );
    }

    /**
     * Vendor-граница Spiral\Auth\TokenStorageInterface (load) — тот же биндинг, что и в
     * AuthBootloader под именем 'cycle'.
     */
    protected function spiralTokenStorage(): SpiralTokenStorage
    {
        return new SpiralTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            authTokenIssuing: $this->authTokenIssuing(),
        );
    }

    private function authTokenIssuing(): AuthTokenIssuing
    {
        return new AuthTokenIssuing(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: new RandomTokenGenerator(),
        );
    }

    /**
     * Публичный контракт User для сценариев Auth: собран поверх реальных сценариев модуля, но с
     * подменённым logger создания — тесты Auth не проверяют журнал соседа.
     */
    protected function users(): UserContract
    {
        return new UserProvider(
            commandBus: $this->commandBus(),
            queryBus: $this->queryBus(),
            createUserHandler: $this->createUserHandler(),
            findUserForAuthHandler: $this->findUserForAuthHandler(),
            checkUsersExistHandler: $this->getContainer()->get(CheckUsersExistHandler::class),
            getUserPublicProfileHandler: $this->getContainer()->get(GetUserPublicProfileHandler::class),
            getUserPublicProfilesHandler: $this->getContainer()->get(GetUserPublicProfilesHandler::class),
        );
    }

    protected function createUserHandler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            logger: new \Psr\Log\NullLogger(),
            localeResolver: $this->getContainer()->get(\App\Shared\Domain\Locale\LocaleResolver::class),
        );
    }

    protected function findUserForAuthHandler(): FindUserForAuthHandler
    {
        return new FindUserForAuthHandler(userRepository: $this->userRepository());
    }

    /**
     * User — чистая доменная сущность без Cycle-разметки, поэтому не может быть сохранена через
     * generic persist(): EntityManager не знает её роль. Хелпер переводит User в Cycle Entity
     * через UserMapper перед прогоном, как это уже делает UserApplicationTestCase.
     */
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

        $this->entityManager()->persist((new UserMapper())->toCycleEntity($user));
        $this->entityManager()->run();

        return $user;
    }

    /**
     * LoginCode — чистая доменная сущность без Cycle-разметки, поэтому персист идёт через
     * LoginCodeMapper так же, как это делает CycleLoginCodeRepository.
     */
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

        $this->entityManager()->persist((new LoginCodeMapper())->toCycleEntity($loginCode));
        $this->entityManager()->run();

        return $loginCode;
    }

    /**
     * RegistrationTicket — чистая доменная сущность без Cycle-разметки, поэтому персист идёт
     * через RegistrationTicketMapper так же, как это делает CycleRegistrationTicketRepository.
     */
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

        $this->entityManager()->persist((new RegistrationTicketMapper())->toCycleEntity($ticket));
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
