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

Переиспользуемая часть ответа — краткая карточка автора, нужная и в ленте, и в детальном просмотре, — лежит в `Application/Result` и вкладывается в Result сценария.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Result;

final readonly class UserCardResult
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
```

## Что повторять

- Имя заканчивается на `Result` и начинается с имени сценария.
- Класс лежит рядом со своим Command или Query.
- Поля имеют точные типы и не содержат Cycle Entity.
- HTTP Resource преобразует Result отдельным методом `fromResult()`.

## Допустимые варианты

Форма ответа сценария лежит рядом со своим Command или Query; переиспользуемая часть ответа — отдельный `final readonly` класс в `Application/Result`, который вкладывается в Result сценария. Для страницы создаётся отдельный Result с типизированными элементами и курсором следующей страницы.
