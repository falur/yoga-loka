<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags;

use App\Modules\Tags\Repository\TagRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов модуля Tags: реальная строка User для FK
 * `tags.created_by_id`, persist-helper с немедленным flush и доступ к репозиторию тегов.
 *
 * Дублирование `createUser`/`persist` с `Tests\Feature\Modules\Posts\PostsRepositoryTestCase`
 * сознательное: тест-инфраструктура модулей изолирована, общую базу не выносим.
 */
abstract class TagsRepositoryTestCase extends DatabaseTestCase
{
    private int $userCounter = 0;

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Сохраняет одну сущность и сразу делает flush, чтобы кросс-табличные FK
     * (без Cycle relation) выполнялись в правильном порядке вставок.
     */
    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function createUser(): User
    {
        $this->userCounter++;

        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('tag.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('tag.user%d', $this->userCounter)),
            locale: Locale::Ru,
        );
    }

    protected function tagRepository(): TagRepository
    {
        return $this->getContainer()->get(TagRepository::class);
    }
}
