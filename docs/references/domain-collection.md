# Типизированная коллекция

## Назначение

Набор однородных значений передаётся именованной коллекцией, а не голым массивом. Тип коллекции называет её элемент, поэтому содержимое видно в сигнатуре.

## Когда применять

Применяй везде, где сценарий, Repository, Reader или Data передают набор: коллекция доменных сущностей и ValueObject — в `Domain/Collection`, набор данных чтения `{Name}DataCollection` — в `Application/Data` рядом со своим Data.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\Post;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Post>
 */
final class PostCollection extends TypedCollection {}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор данных чтения одной выборки. Ряды в объекты превращает фабрика самого Data,
 * поэтому ключи ряда читаются в одном месте.
 *
 * @extends TypedCollection<int, PostData>
 */
final class PostDataCollection extends TypedCollection
{
    /**
     * Карта «запись -> список идентификаторов» держит значением объект `PostRelatedIdsData`,
     * а не `list<string>` напрямую: тип контракта проекта не допускает вложенные массивы
     * (`array<string, list<string>>` не проходит правило `noNestedArrayType`).
     *
     * @param iterable<array-key, array<non-empty-string, scalar|null>> $rows
     * @param array<string, PostRelatedIdsData> $mediaIdsByPost
     */
    public static function fromDatabaseRows(iterable $rows, array $mediaIdsByPost): self
    {
        $postDataCollection = new self();

        foreach ($rows as $row) {
            $postDataCollection->push(PostData::fromDatabaseRow(
                row: $row,
                mediaIds: ($mediaIdsByPost[(string) $row['id']] ?? new PostRelatedIdsData(ids: []))->ids,
            ));
        }

        return $postDataCollection;
    }

    /** @return list<string> */
    public function authorIds(): array
    {
        return $this
            ->mapToList(static fn(PostData $postData): string => $postData->authorId);
    }
}
```

## Что повторять

- Класс `final`, наследует `App\Shared\Domain\Collection\TypedCollection` и объявляет `@extends TypedCollection<int, {Element}>`.
- Имя коллекции — имя элемента плюс `Collection`: `PostCollection`, `PostDataCollection`.
- Доменная коллекция лежит в `Domain/Collection`, коллекция данных чтения — в `Application/Data`, коллекция переиспользуемых частей ответа — в `Application/Result`.
- Набор доменных значений передаётся этой коллекцией, а не массивом и не голым `Collection`.
- Преобразование в список делает `mapToList()`; к карте, где ключ несёт смысл, он не применяется.
- Если значение уже коллекция, используются её методы: перехода `коллекция -> массив -> коллекция` нет.
- Тип коллекции сохраняется в `CursorSlice::fromOverfetched()`, поэтому `$slice->items` кладётся в `{Name}PageData` как есть.

## Допустимые варианты

Коллекция может нести собственные методы выборки и агрегации над своими элементами, если они не содержат бизнес-правил модуля; правило принадлежит Entity, `Domain/Service` или сценарию. Коллекция без владельца среди модулей находится в `Shared/Domain/Collection`.
