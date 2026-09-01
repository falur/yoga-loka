# Entity columns

## Назначение

Класс columns хранит имя таблицы и имена её колонок и убирает строковые литералы из запросов.

## Когда применять

Применяй для таблицы и колонок, к которым обращаются Cycle Entity, Cycle Repository и Reader.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Columns;

final class UserColumns
{
    public const string TABLE = 'users';

    public const string ID = 'id';
    public const string NAME = 'name';

    private function __construct() {}
}
```

## Что повторять

- Имя имеет форму `{Entity}Columns`.
- Для каждой таблицы создаётся отдельный `final`-класс.
- Первая константа `TABLE` хранит имя таблицы, следом идут имена колонок.
- Константы типизированы как `string`.
- Класс содержит только реально используемые имена колонок.

## Допустимые варианты

Если колонка нигде не используется в запросе, константу можно не добавлять. Enum здесь не нужен: Cycle принимает строковое имя колонки.

В миграции имя таблицы и колонки остаются строковым литералом: миграция фиксирует схему на момент применения и не следует за переименованием.
