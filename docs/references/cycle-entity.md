# Cycle Entity

## Назначение

Cycle Entity описывает таблицу и хранит состояние строки. Бизнес-поведения и доменных фабрик в нём нет.

## Когда применять

Применяй для хранения чистой Domain Entity через Cycle ORM.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

#[Entity(
    role: 'user',
    table: UserColumns::TABLE,
    repository: CycleUserRepository::class,
)]
final class UserCycleEntity
{
    #[Column(type: 'uuid', name: UserColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string', name: UserColumns::NAME)]
    public string $name;
}
```

## Что повторять

- Имя имеет форму `{Entity}CycleEntity`.
- Класс находится в `Infrastructure/Persistence/Cycle/Entity`.
- Атрибуты Cycle находятся только здесь.
- Поля отражают хранение и не содержат бизнес-методов.
- Domain Entity собирает Mapper.

## Допустимые варианты

Backed enum и `DateTimeImmutable` могут использоваться как типы полей, если Cycle преобразует их однозначно. Для сложного JSON или шифрования применяется отдельный Typecast.
