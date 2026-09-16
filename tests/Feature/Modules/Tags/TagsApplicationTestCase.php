<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
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

    /**
     * User — чистая доменная сущность без Cycle-разметки, поэтому не может быть сохранена
     * через generic persist(): EntityManager не знает её роль. Хелпер переводит User в Cycle
     * Entity через UserMapper перед сохранением, по тому же приёму, что persistTag() ниже.
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

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function tagRepository(): TagRepository
    {
        return $this->getContainer()->get(TagRepository::class);
    }
}
