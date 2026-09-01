# Entity

## Назначение

Доменная сущность хранит состояние и защищает бизнес-переходы. Она не является моделью Cycle ORM.

## Когда применять

Применяй для объекта с устойчивой идентичностью и жизненным циклом. Если объект управляет согласованностью группы внутренних сущностей, он является корнем агрегата.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\UserDisplayName;
use App\Shared\Domain\ValueObject\UserId;

final class User
{
    private function __construct(
        public private(set) UserId $id,
        public private(set) UserDisplayName $name,
    ) {}

    public static function register(UserId $id, UserDisplayName $name): self
    {
        return new self(
            id: $id,
            name: $name,
        );
    }

    public static function restore(UserId $id, UserDisplayName $name): self
    {
        return new self(
            id: $id,
            name: $name,
        );
    }

    public function rename(UserDisplayName $name): void
    {
        $this->name = $name;
    }
}
```

## Что повторять

- В классе нет атрибутов ORM и инфраструктурных интерфейсов.
- Фабрика создания называется бизнес-действием, например `register()` или `place()`; техническое восстановление называется `restore()`.
- Состояние меняется именованным доменным методом.
- В сигнатурах используются доменные типы, а не примитивы хранения.

## Допустимые варианты

Фабрика `restore()` может быть заменена отдельным reconstitution-механизмом mapper-а. Внутренняя сущность агрегата не получает собственный Repository без отдельного жизненного цикла.
