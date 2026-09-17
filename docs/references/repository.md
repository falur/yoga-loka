# Repository

## Назначение

Repository скрывает хранение агрегата за доменным интерфейсом.

## Когда применять

Применяй для загрузки и сохранения корня агрегата. Не создавай Repository только потому, что существует таблица.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\ValueObject\UserId;

interface UserRepository
{
    public function findById(UserId $userId): User|null;

    public function save(User $user): void;
}
```

## Что повторять

- Интерфейс находится в домене и возвращает доменную модель.
- Методы названы языком сценария, а не API ORM.
- Сохранение принимает корень агрегата целиком.
- Cycle, EntityManager и модель хранения отсутствуют в сигнатуре.

## Допустимые варианты

Для сложной проекции чтения Repository не расширяется: порт объявляется как `{Name}Reader` в `Application/Contract`, реализуется в `Infrastructure/Persistence/Cycle/Read` и возвращает Data — см. карточку [Reader](reader.md). Reader не подменяет Repository агрегата.
