# Reader

## Назначение

Reader читает таблицы своего модуля и отдаёт Data. Это единственный способ получить данные для показа: доменные агрегаты ради ответа не загружаются.

## Когда применять

Применяй, когда Query handler собирает ответ. Интерфейс объявляется в `Application/Contract`, реализация Cycle лежит в `Infrastructure/Persistence/Cycle/Read`.

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
use App\Shared\Infrastructure\Cycle\WhenQuery;

/**
 * Чтение записей автора: сами записи и идентификаторы их вложений. Только свои таблицы,
 * соседей не спрашивает, доменные сущности не создаёт.
 */
final readonly class CyclePostReader implements PostReader
{
    public function __construct(
        private WhenQuery $query,
    ) {}

    public function userFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData
    {
        $rows = $this->query
            ->select(PostColumns::ID, PostColumns::NAME, PostColumns::AUTHOR_ID)
            ->from(PostColumns::TABLE)
            ->where(PostColumns::AUTHOR_ID, $ownerUserId)
            ->cursorById(cursor: $cursor, limit: $limit + 1)
            ->fetchAll();

        $mediaIds = $this->mediaIds(\array_column($rows, PostColumns::ID));

        return PostPageData::fromDatabaseRows(rows: $rows, mediaIds: $mediaIds, limit: $limit);
    }

    /**
     * Вложения всех записей страницы одним запросом.
     *
     * @param list<string> $postIds
     *
     * @return array<string, list<string>>
     */
    private function mediaIds(array $postIds): array
    {
        $byPost = [];

        $rows = $this->query
            ->select(PostMediaColumns::POST_ID, PostMediaColumns::MEDIA_ID)
            ->from(PostMediaColumns::TABLE)
            ->where(PostMediaColumns::POST_ID, 'in', $postIds)
            ->fetchAll();

        foreach ($rows as $row) {
            $byPost[(string) $row[PostMediaColumns::POST_ID]][] = (string) $row[PostMediaColumns::MEDIA_ID];
        }

        return $byPost;
    }
}
```

## Что повторять

- Интерфейс называется `{Name}Reader` и лежит в `Application/Contract`, реализация — `Cycle{Name}Reader` в `Infrastructure/Persistence/Cycle/Read`.
- Reader читает только таблицы своего модуля; join через границу модуля запрещён.
- Возвращает Data, никогда Entity и никогда Result.
- Не создаёт доменные сущности, не применяет бизнес-правила, не вызывает соседние модули, шину и Repository.
- Ряд выборки в объект превращает фабрика Data, а не сам Reader.
- Имена колонок берутся из `{Entity}Columns`, а не из строковых литералов.
- Условный фильтр и курсорную страницу пиши через `when()` и `cursorById()`, а не через `if` в теле метода.

## Допустимые варианты

Один Reader на агрегат, метод — на сценарий. Разные условия показа это разные методы: `userFeed()` для чужой ленты и `myFeed()` для своей, а не общий метод с вычислением роли зрителя.
