<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Integration\Cycle;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов модуля Tags: реальная строка User для FK
 * `tags.created_by_id`, persist-helper с немедленным flush и доступ к репозиторию тегов.
 *
 * Дублирование `createUser`/`persist` с `App\Modules\Posts\Tests\Integration\Cycle\PostsRepositoryTestCase`
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
     *
     * User — чистая доменная сущность без Cycle-разметки, поэтому не может быть сохранена через
     * generic persist(): EntityManager не знает её роль. Хелпер переводит User в Cycle Entity
     * через UserMapper перед сохранением, по тому же приёму, что persistTag() ниже.
     */
    protected function persist(object $entity): void
    {
        $this->entityManager()->persist(match (true) {
            $entity instanceof User => (new UserMapper())->toCycleEntity($entity),
            default => throw new \InvalidArgumentException(\sprintf(
                'persist() не знает Mapper для сущности %s.',
                $entity::class,
            )),
        });
        $this->entityManager()->run();
    }

    /**
     * Tag — чистая доменная сущность без Cycle-разметки, поэтому в отличие от User
     * не может быть сохранена через generic persist(): EntityManager не знает её роль.
     * Хелпер переводит Tag в CycleTagEntity через TagMapper перед сохранением.
     */
    protected function persistTag(Tag $tag): void
    {
        $this->entityManager()->persist((new TagMapper())->toCycleEntity($tag));
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

    /**
     * Удаляет строку User по её текущему PK — домен больше не хранит трекнутый Cycle-объект,
     * поэтому generic entityManager()->delete($user) не знает роль сущности.
     */
    protected function deleteUser(User $user): void
    {
        /** @var ORMInterface $orm */
        $orm = $this->getContainer()->get(ORMInterface::class);
        /** @var Select<CycleUserEntity> $select */
        $select = new Select($orm, CycleUserEntity::class);
        $cycleUser = $select->wherePK($user->id->value())->fetchOne();

        if ($cycleUser !== null) {
            $this->entityManager()->delete($cycleUser);
        }
    }

    protected function tagRepository(): TagRepository
    {
        return $this->getContainer()->get(TagRepository::class);
    }
}
