# Domain Enum

## Назначение

Закрытый набор доменных значений выражается enum, а не строками, разбросанными по коду.

## Когда применять

Применяй, когда новый вариант значения может появиться только после изменения кода.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Enum;

enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Deleted = 'deleted';

    public function canPublish(): bool
    {
        return $this === self::Active;
    }
}
```

## Что повторять

- Имя описывает доменное понятие и не содержит технических слов.
- Backed value записывается в `snake_case`.
- Поведение допустимо, если зависит только от значения enum.
- Ветвление выполняется исчерпывающим `match` без `default`.

## Допустимые варианты

Общий нейтральный enum может находиться в `Shared/Domain/Enum`. Если варианты добавляются пользователем без выпуска кода, нужен справочник в базе, а не enum.
