<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Integration\Spiral;

use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthQuery;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthResult;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class FindUserForAuthHandlerTest extends DatabaseTestCase
{
    public function testReturnsViewWithSignInAllowedForActiveUser(): void
    {
        $activeUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
        $activeUser->confirmEmail();
        $this->persist($activeUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'ACTIVE@example.com'));

        self::assertInstanceOf(FindUserForAuthResult::class, $view);
        self::assertSame($activeUser->id->value(), $view->userId);
        self::assertTrue($view->canSignIn);
    }

    public function testReturnsViewWithSignInDeniedForBannedUser(): void
    {
        $bannedUser = $this->createUser(email: 'banned@example.com', nickname: 'banned.user');
        $bannedUser->confirmEmail();
        $bannedUser->ban();
        $this->persist($bannedUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'banned@example.com'));

        self::assertInstanceOf(FindUserForAuthResult::class, $view);
        self::assertFalse($view->canSignIn);
    }

    public function testReturnsViewWithSignInDeniedForDeletedUser(): void
    {
        $deletedUser = $this->createUser(email: 'deleted@example.com', nickname: 'deleted.user');
        $deletedUser->confirmEmail();
        $deletedUser->markDeleted(new \DateTimeImmutable());
        $this->persist($deletedUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'deleted@example.com'));

        self::assertInstanceOf(FindUserForAuthResult::class, $view);
        self::assertFalse($view->canSignIn);
    }

    public function testReturnsNullForMissingUser(): void
    {
        self::assertNull($this->handler()->handle(new FindUserForAuthQuery(email: 'absent@example.com')));
    }

    private function handler(): FindUserForAuthHandler
    {
        return new FindUserForAuthHandler(userRepository: $this->userRepository());
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    /**
     * User — чистая доменная сущность без Cycle-разметки, поэтому не может быть сохранена через
     * generic persist(): EntityManager не знает её роль. Хелпер переводит User в Cycle Entity
     * через Mapper перед постановкой в очередь EntityManager.
     */
    private function persist(User $user): void
    {
        $this->entityManager()->persist((new UserMapper())->toCycleEntity($user));
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
}
