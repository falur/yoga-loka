# Reader

## Назначение

Reader читает таблицы своего модуля и отдаёт Data — типизированные значения чтения без доменного поведения.

## Когда применять

Применяй, когда ответу нужен признак, которого в агрегате нет: флаг по зрителю, счётчик из соседней таблицы, склейка нескольких своих таблиц. Если ответу хватает полей агрегата, Query handler читает через Repository, и Reader не заводится.

Интерфейс объявляется в `Application/Contract`, реализация Cycle лежит в `Infrastructure/Persistence/Cycle/Read`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

use App\Modules\Posts\Application\Data\PostPageData;

interface PostReader
{
    public function userFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Read;

use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Application\Data\PostDataCollection;
use App\Modules\Posts\Application\Data\PostPageData;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMediaColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORMInterface;

/**
 * Чтение записей автора: сами записи и идентификаторы их вложений. Только свои таблицы,
 * соседей не спрашивает, доменные сущности не создаёт.
 */
final readonly class CyclePostReader implements PostReader
{
    public function __construct(
        private ORMInterface $orm,
    ) {}

    #[\Override]
    public function userFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData
    {
        // Запас в один ряд: по лишнему ряду CursorSlice видит, есть ли следующая страница,
        // и отдельный COUNT не нужен.
        $rows = $this->postSelect()
            ->where(PostColumns::AUTHOR_ID, $ownerUserId)
            ->cursorById(cursor: $cursor, limit: $limit + 1)
            ->fetchData();

        $overfetched = PostDataCollection::fromDatabaseRows(
            rows: $rows,
            mediaIdsByPost: $this->mediaIdsByPost($rows),
        );

        $slice = CursorSlice::fromOverfetched(
            overfetched: $overfetched,
            limit: $limit,
            cursorOf: static fn(PostData $postData): string => $postData->id,
        );

        return new PostPageData(
            posts: $slice->items,
            nextCursor: $slice->nextCursor,
        );
    }

    /**
     * Вложения всех записей страницы одним запросом.
     *
     * Ключ ряда `fetchData()` — имя поля Cycle Entity (`postId`), а имя колонки из каталога
     * (`post_id`) идёт в условие запроса: это разные имена и подменять их нельзя.
     *
     * @param iterable<array-key, array<non-empty-string, scalar|null>> $rows
     *
     * @return array<string, list<string>>
     */
    private function mediaIdsByPost(iterable $rows): array
    {
        $postIds = [];

        foreach ($rows as $row) {
            $postIds[] = (string) $row['id'];
        }

        if ($postIds === []) {
            return [];
        }

        $mediaIdsByPost = [];

        $mediaRows = $this->postMediaSelect()
            ->where(PostMediaColumns::POST_ID, 'in', new Parameter($postIds))
            ->fetchData();

        foreach ($mediaRows as $mediaRow) {
            $mediaIdsByPost[(string) $mediaRow['postId']][] = (string) $mediaRow['mediaId'];
        }

        return $mediaIdsByPost;
    }

    /** @return WhenSelect<CyclePostEntity> */
    private function postSelect(): WhenSelect
    {
        return new WhenSelect(orm: $this->orm, role: CyclePostEntity::class);
    }

    /** @return WhenSelect<CyclePostMediaEntity> */
    private function postMediaSelect(): WhenSelect
    {
        return new WhenSelect(orm: $this->orm, role: CyclePostMediaEntity::class);
    }
}
```

## Что повторять

- Интерфейс называется `{Name}Reader` и лежит в `Application/Contract`, реализация — `Cycle{Name}Reader` в `Infrastructure/Persistence/Cycle/Read`.
- Reader читает только таблицы своего модуля; join через границу модуля запрещён.
- Возвращает Data, никогда Entity и никогда Result.
- Не создаёт доменные сущности, не применяет бизнес-правила, не вызывает соседние модули, шину и Repository.
- Ряд выборки в объект превращает фабрика самого Data, а не Reader.
- Имена таблиц и колонок в запросе берутся из `{Entity}Columns`, а не из строковых литералов; ключ ряда `fetchData()` — имя поля Cycle Entity и с именем колонки не совпадает.
- Условный фильтр пишется через `WhenSelect::when()`, а не через `if` в теле метода.
- Курсорная страница строится ровно двумя общими примитивами: `WhenSelect::cursorById()` выбирает `limit + 1` рядов, `CursorSlice::fromOverfetched()` отрезает видимую часть и считает курсор. Своей арифметики страницы ни в Reader, ни в фабрике Data нет.
- `CursorSlice` сохраняет тип переданной коллекции, поэтому `$slice->items` кладётся в `{Name}PageData` без промежуточного перехода в массив.

## Допустимые варианты

Один Reader на агрегат, метод — на сценарий. Разные условия показа это разные методы: `userFeed()` для чужой ленты и `myFeed()` для своей, а не общий метод с вычислением роли зрителя.

Запрос строится как `WhenSelect` поверх Cycle Entity своего модуля: `fetchData()` отдаёт ряды и не создаёт объектов, ключ ряда — имя поля Cycle Entity. Если понадобится построитель запроса поверх сырых таблиц, общий примитив сначала заводится в `Shared/Infrastructure/Persistence/Cycle` и только после этого показывается в карточке.
