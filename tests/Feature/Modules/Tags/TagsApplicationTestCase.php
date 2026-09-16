<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags;

use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов сценариев модуля Tags: реальный пользователь для FK created_by_id,
 * persist-helper с немедленным flush и доступ к репозиторию тегов.
 */
abstract class TagsApplicationTestCase extends DatabaseTestCase
{
    private int $userCounter = 0;

    protected function persistUser(): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('tag.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('tag.user%d', $this->userCounter)),
            locale: Locale::Ru,
        );
        $this->persist($user);

        return $user;
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function tagRepository(): TagRepository
    {
        return $this->getContainer()->get(TagRepository::class);
    }
}
