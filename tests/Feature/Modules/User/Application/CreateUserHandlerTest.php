<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Command\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\ReservedNicknameMapper;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Modules\User\Domain\Exception\EmailAlreadyTakenException;
use App\Modules\User\Domain\Exception\NicknameAlreadyTakenException;
use App\Shared\Domain\Locale\LocaleResolver;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;

final class CreateUserHandlerTest extends DatabaseTestCase
{
    public function testCreatesActiveUserAndReturnsId(): void
    {
        $createUserResult = $this->handler()->handle(new CreateUserCommand(
            email: 'new@example.com',
            name: 'Йога Тест',
            nickname: 'new.nick',
            locale: 'ru',
        ));

        self::assertTrue(AbstractUuidV7Id::isUuidV7($createUserResult->userId));

        $this->cleanOrmHeap();
        $createdUser = $this->userRepository()->findById(UserId::fromString($createUserResult->userId));

        self::assertInstanceOf(User::class, $createdUser);
        self::assertSame(UserStatus::Active, $createdUser->status);
        self::assertSame('new@example.com', $createdUser->email->value());
        self::assertSame('new.nick', $createdUser->nickname->value());
        self::assertSame(Locale::Ru, $createdUser->locale);
    }

    public function testFallsBackToDefaultLocaleForUnsupportedValue(): void
    {
        $createUserResult = $this->handler()->handle(new CreateUserCommand(
            email: 'fallback@example.com',
            name: 'Йога Тест',
            nickname: 'fallback.nick',
            locale: 'fr',
        ));

        $this->cleanOrmHeap();
        $createdUser = $this->userRepository()->findById(UserId::fromString($createUserResult->userId));

        self::assertInstanceOf(User::class, $createdUser);
        self::assertSame(
            Locale::from($this->getContainer()->get(LocaleConfig::class)->default),
            $createdUser->locale,
        );
    }

    public function testRejectsTakenEmail(): void
    {
        $this->persistUser($this->createUser(email: 'taken@example.com', nickname: 'someone'));

        $this->expectException(EmailAlreadyTakenException::class);
        $this->expectExceptionMessage('app.user.email_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'TAKEN@example.com',
            name: 'Йога Тест',
            nickname: 'free.nick',
            locale: 'ru',
        ));
    }

    public function testRejectsTakenNickname(): void
    {
        $this->persistUser($this->createUser(email: 'owner@example.com', nickname: 'busy.nick'));

        $this->expectException(NicknameAlreadyTakenException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'BUSY.NICK',
            locale: 'ru',
        ));
    }

    public function testRejectsReservedNickname(): void
    {
        $this->persistReservedNickname(ReservedNickname::create(UserNickname::fromString('reserved')));

        $this->expectException(NicknameAlreadyTakenException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'reserved',
            locale: 'ru',
        ));
    }

    private function handler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            logger: new NullLogger(),
            localeResolver: $this->getContainer()->get(LocaleResolver::class),
        );
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::create(
            name: UserName::fromString('Существующий'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    /**
     * User и ReservedNickname — чистые доменные сущности без Cycle-разметки, поэтому не могут
     * быть сохранены через generic persist(): EntityManager не знает их роль. Хелперы переводят
     * их в Cycle Entity через Mapper перед постановкой в очередь EntityManager.
     */
    private function persistUser(User $user): void
    {
        $this->entityManager()->persist((new UserMapper())->toCycleEntity($user));
        $this->entityManager()->run();
    }

    private function persistReservedNickname(ReservedNickname $reservedNickname): void
    {
        $this->entityManager()->persist((new ReservedNicknameMapper())->toCycleEntity($reservedNickname));
        $this->entityManager()->run();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    private function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}
