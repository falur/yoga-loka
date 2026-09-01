# Mapper

## Назначение

Mapper преобразует модель хранения Cycle в чистую доменную сущность и обратно.

## Когда применять

Применяй в реализации Repository, когда Domain отделён от атрибутов Cycle.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\UserDisplayName;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\UserCycleEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class UserMapper
{
    public function toDomain(UserCycleEntity $cycleEntity): User
    {
        return User::restore(
            id: UserId::fromString($cycleEntity->id),
            name: UserDisplayName::fromString($cycleEntity->name),
        );
    }

    public function toCycleEntity(
        User $user,
        UserCycleEntity|null $cycleEntity = null,
    ): UserCycleEntity {
        $cycleEntity ??= new UserCycleEntity();
        $cycleEntity->id = $user->id->value();
        $cycleEntity->name = $user->name->value();

        return $cycleEntity;
    }
}
```

## Что повторять

- Mapper находится в Infrastructure рядом с Cycle.
- Преобразование в Domain проходит через доменные фабрики.
- Методы называются `toDomain()` и `toCycleEntity()`.
- Преобразование в Cycle Entity использует явные значения доменных типов.
- Mapper не содержит бизнес-решений и запросов к БД.

## Допустимые варианты

Mapper обновляет переданный `Cycle Entity` или создаёт новый. Для вложенных частей агрегата используются отдельные приватные преобразования, но граница сохранения остаётся у корня.
