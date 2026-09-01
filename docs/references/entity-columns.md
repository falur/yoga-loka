# Entity columns

## Назначение

Класс columns хранит имена колонок одного Cycle Entity и убирает строковые литералы из запросов.

## Когда применять

Применяй для колонок, к которым обращаются Cycle Repository и read-запросы.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Columns;

final class UserColumns
{
    public const string ID = 'id';
    public const string NAME = 'name';

    private function __construct() {}
}
```

## Что повторять

- Имя имеет форму `{Entity}Columns`.
- Для каждой таблицы создаётся отдельный `final`-класс.
- Константы типизированы как `string`.
- Класс содержит только реально используемые имена колонок.

## Допустимые варианты

Если колонка нигде не используется в запросе, константу можно не добавлять. Enum здесь не нужен: Cycle принимает строковое имя колонки.
