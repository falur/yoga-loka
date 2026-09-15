# Data

## Назначение

Data — данные, которые Reader читает из таблиц своего модуля и отдаёт в Application. Это форма чтения без доменного поведения: она не знает ни про соседние модули, ни про то, как будет выглядеть ответ.

## Когда применять

Применяй, когда Query handler читает свои данные через Reader. Data содержит собственные поля записи и идентификаторы чужих частей, которые handler дочитает через `Public` соседей.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

/**
 * Запись как она лежит в своих таблицах: собственные поля и идентификаторы чужих частей.
 * Имени автора и пути к файлу здесь нет — это данные соседних модулей.
 */
final readonly class PostData
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $authorId,
        public array $mediaIds,
    ) {}

    /**
     * Единственное место, где ряд выборки превращается в объект. Ключ ряда — имя поля
     * Cycle Entity, а не имя колонки: `authorId`, а не `author_id`.
     *
     * @param array<non-empty-string, scalar|null> $row
     * @param list<string> $mediaIds
     */
    public static function fromDatabaseRow(array $row, array $mediaIds): self
    {
        return new self(
            id: (string) $row['id'],
            name: (string) $row['name'],
            authorId: (string) $row['authorId'],
            mediaIds: $mediaIds,
        );
    }
}
```

## Что повторять

- Имя заканчивается на `Data`, класс лежит в `Application/Data`.
- Ряд выборки превращает в объект фабрика `fromDatabaseRow()`; Reader ключи ряда не читает.
- Поля имеют точные типы: строки, enum, `DateTimeImmutable`, списки идентификаторов.
- Чужая часть представлена идентификатором, а не значением: имя автора и путь к файлу дочитывает handler.
- Доменных типов, Cycle Entity и бизнес-правил в Data нет.

## Допустимые варианты

Набор передаётся типизированной коллекцией `{Name}DataCollection` — см. карточку [Типизированная коллекция](domain-collection.md). Для страницы создаётся `{Name}PageData` с этой коллекцией и курсором следующей страницы; он же отдаёт наборы чужих идентификаторов, чтобы handler дочитал их одним вызовом на соседний модуль. Сам курсор и нарезку страницы считает `CursorSlice::fromOverfetched()` в Reader, а не фабрика Data.
