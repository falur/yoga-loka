# Result DTO

## Назначение

Result DTO задаёт результат одного Command или Query, когда возвращать Domain Entity недостаточно или нельзя.

## Когда применять

Применяй, когда результат объединяет несколько источников, содержит вычисленные поля или намеренно отличается от Entity.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUser;

use App\Modules\User\Domain\Enum\UserStatus;

final readonly class GetUserResult
{
    public function __construct(
        public string $id,
        public string $name,
        public UserStatus $status,
    ) {}
}
```

## Что повторять

- Имя заканчивается на `Result` и начинается с имени сценария.
- Класс лежит рядом со своим Command или Query.
- Поля имеют точные типы и не содержат Cycle Entity.
- HTTP Resource преобразует Result отдельным методом `fromResult()`.

## Допустимые варианты

Простой Query может вернуть Domain Entity. Для страницы создаётся отдельный Result с типизированными элементами и курсором; общий каталог результатов не создаётся.
