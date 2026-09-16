# Cycle Repository

## Назначение

Cycle Repository реализует Domain Repository, использует Mapper и скрывает Cycle ORM от Application.

## Когда применять

Применяй для хранения корня агрегата через Cycle ORM.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;

/** @extends AbstractRepository<CycleUserEntity> */
final class CycleUserRepository extends AbstractRepository implements UserRepository
{
    /** @param Select<CycleUserEntity> $select */
    public function __construct(
        Select $select,
        ORMInterface $orm,
        string $role,
        private UserMapper $userMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(UserId $userId): User|null
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([
            UserColumns::ID => $userId->value(),
        ]);

        return $cycleEntity === null
            ? null
            : $this->userMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function save(User $user): void
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([
            UserColumns::ID => $user->id->value(),
        ]);

        $this->entityManager
            ->persist($this->userMapper->toCycleEntity(
                user: $user,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }
}
```

## Что повторять

- Имя имеет форму `Cycle{Entity}Repository`.
- Класс реализует один Domain Repository.
- Cycle Entity преобразуется только через Mapper.
- Имена колонок берутся из `{Entity}Columns`.
- Cycle-типы не выходят из Infrastructure.
- Базовый класс `AbstractRepository` берётся из `App\Shared\Infrastructure\Persistence\Cycle`: его `select()` возвращает `WhenSelect` с `when()` и `cursorById()`.
- Репозиторий не открывает и не закрывает бизнес-транзакцию: прогон `EntityManager` вкладывается
  в транзакцию, открытую границей вызова, и выполняется своей, когда открытой нет.

## Допустимые варианты

Тяжёлая проекция чтения решается не здесь: порт объявляется как `{Name}Reader` в `Application/Contract`, реализуется как `Cycle{Name}Reader` в `Infrastructure/Persistence/Cycle/Read` и возвращает Data — см. карточку [Reader](reader.md). Cycle Entity наружу не выходит.
