---
name: laravel-collections
description: >-
  Справочник по работе с Illuminate\Support\Collection и типизированными доменными
  коллекциями проекта: конвейеры map/filter/reduce вместо foreach и array-функций,
  запрет round-trip «коллекция → массив → коллекция», cursor-пагинация через
  take/last/count, toBase() для смены типа элемента, корректные дженерики под
  PHPStan level max. Используй при выборках, трансформациях, группировке и пагинации
  над коллекциями.
user-invocable: false
---

# Laravel Collections в проекте

Проект использует `illuminate/collections` (^13) **без larastan**. PHPStan level max
читает дженерики прямо из PHPDoc пакета. Каждый набор сущностей/VO — отдельная
типизированная коллекция (`PostCollection extends Collection<int, Post>`), репозитории
и связи Cycle ORM возвращают именно её, а не `array` и не голый `Collection`.

Главный принцип: **если на входе коллекция и на выходе коллекция — весь конвейер живёт
на коллекции**. Разворачивать в массив через `->all()` только тогда, когда наружу
действительно нужен `array`/`list<T>` (тип возврата метода, чужой API).

## 1. Конвейер вместо foreach и array-функций

`foreach` — только ради побочных эффектов (`persist`, отправка в очередь, лог). Любая
чистая выборка/трансформация — методы коллекции.

```php
// Плохо: развернули ради array-функции
$active = array_filter($users->all(), static fn(User $u): bool => $u->isActive());

// Хорошо
$active = $users->filter(static fn(User $user): bool => $user->isActive());

// Плохо: foreach ради сбора значений
$ids = [];
foreach ($posts as $post) {
    $ids[] = $post->id->value();
}

// Хорошо
$ids = $posts->map(static fn(Post $post): string => $post->id->value());
```

Полезные методы: `map`, `filter`, `reject`, `first`, `last`, `firstWhere`, `contains`,
`every`, `pluck`, `keyBy`, `groupBy`, `unique`, `values`, `sortBy`, `take`, `slice`,
`partition`, `isEmpty`, `isNotEmpty`, `count`, `sum`, `reduce`.

## 2. Запрет round-trip «коллекция → массив → коллекция»

Антипаттерн: взять `->all()`, обработать массив (`\count()`, `array_slice()`,
индексирование), а потом снова завернуть в `new SomeCollection($array)`.

```php
// ПЛОХО: гоняем туда-сюда
$page = $repo->findVisibleByUserId(...)->all();          // коллекция -> массив
if (\count($page) <= $limit) { ... }                     // \count поверх массива
$visible = \array_slice(array: $page, offset: 0, length: $limit);
$result = new PostCollection($visible);                   // массив -> снова коллекция
$cursor = $visible[\count($visible) - 1]->id->value();   // индексирование массива

// ХОРОШО: всё на коллекции
$page = $repo->findVisibleByUserId(...);                 // уже PostCollection
$visible = $page->take($limit);                          // тоже PostCollection
$cursor = $page->count() > $limit ? $visible->last()?->id->value() : null;
```

Если коллекцию нужно передать в конструктор другой типизированной коллекции — передавай
саму коллекцию (`Collection`/`Arrayable`), а не `->all()`:

```php
// Плохо
new AuthTokenCollection($sessionTokens->all());
// Хорошо
new AuthTokenCollection($sessionTokens);
```

## 3. Cursor-пагинация — через общие примитивы, не копипастом

Схема: запросить `limit + 1`, понять, есть ли следующая страница, отдать первые `limit`
и курсор последней отданной записи. Эта механика **не дублируется** в каждом репозитории и
обработчике — для неё есть два общих примитива.

### Репозиторий: `WhenSelect::cursorById()`

`App\Shared\Infrastructure\Persistence\Cycle\WhenSelect::cursorById($cursor, $limit)` — последнее звено
цепочки `select()`: инкапсулирует `orderBy('id','DESC')`, `where('id','<',$cursor)` при курсоре
и `limit`. Курсор — `value()` UUID v7 id (см. правило «Cursor-пагинация по UUID v7 `id`»).

```php
public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
{
    return new CommentCollection(
        $this->select()
            ->where('parent_comment_id', $parentId->value())
            ->where('deleted_at', '=', null)
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll(),
    );
}
```

Не писать вручную блок `->when(...cursor...)->orderBy('id','DESC')->limit($limit)` в каждом методе.

### Обработчик: `CursorSlice::fromOverfetched()`

`App\Shared\Domain\Pagination\CursorSlice::fromOverfetched(overfetched:, limit:, cursorOf:)` —
получает `overfetch`-набор (репозиторий запрашивает `limit + 1`), отрезает первые `limit` и
вычисляет курсор. Тип конкретной коллекции сохраняется: `$slice->items` — та же
`PostCollection`/`CommentCollection`/`NotificationCollection`.

```php
public function handle(GetUserFeedQuery $query): GetUserFeedResult
{
    $slice = CursorSlice::fromOverfetched(
        overfetched: $this->postRepository->findVisibleByUserId(
            userId: $owner,
            status: $status,
            excludeStatus: $excludeStatus,
            cursor: $cursor,
            limit: $query->limit + 1,
        ),
        limit: $query->limit,
        cursorOf: static fn(Post $post): string => $post->id->value(),
    );

    return new GetUserFeedResult(
        posts: $this->postViewAssembler->fromPosts(posts: $slice->items, viewer: $viewer),
        nextCursor: $slice->nextCursor,
    );
}
```

Не писать вручную `$page->take($limit)` + `$page->count() > $limit` + `$visible->last()?->id->value()`
в каждом обработчике. Оба примитива применены в `GetUserFeedHandler`, `GetPostCommentsHandler`,
`GetCommentRepliesHandler`, `ListNotificationsHandler` и cursor-методах репозиториев Posts/Notifications.

## 4. Смена типа элемента: `toBase()->map()`

`final`-коллекция (`AuthSessionCollection extends Collection<int, AuthSession>`)
зафиксировала тип элемента. `->map()` в Illuminate возвращает `static<...>`, поэтому
маппинг в **другой** тип (Resource, DTO) на типизированной коллекции ломает дженерики
PHPStan. Решение — сначала `->toBase()` (базовый `Collection`), потом `map`:

```php
// Маппинг типизированной коллекции в list ресурсов — канонично через mapToList()
$sessionResources = $userSessions->mapToList(
    static fn(AuthSession $session): SessionResource
        => SessionResource::fromSession(session: $session, currentSessionId: $current),
);
```

`mapToList()` — метод базового `TypedCollection`: он прячет связку
`\array_values($coll->toBase()->map($fn)->all())` в одном месте и сразу даёт `list<TNew>`.
Для случая, когда на выходе остаётся **коллекция** (а не список), по-прежнему нужен
`->toBase()->map(...)`: он заменяет ручной `new Collection($collection->all())` — базовый
`Collection` без разворачивания в массив. Та же идиома для группировки:

```php
$userSessions = new AuthSessionCollection(
    $tokens
        ->toBase()
        ->groupBy(static fn(AuthToken $token): string => $token->sessionId->value())
        ->map(static fn(Collection $sessionTokens): AuthSession
            => AuthSession::fromTokens(new AuthTokenCollection($sessionTokens)))
        ->values(),
);
```

Если на выходе нужна **другая типизированная коллекция**, передавай результат
`toBase()->map(...)` прямо в её конструктор — без `->all()`:

```php
return new UserPublicProfileCollection(
    $this->userRepository->findByIds(...$userIds)
        ->toBase()
        ->map(fn(User $user): UserPublicProfileView => $this->assembler->fromUser($user)),
);
```

## 5. Когда `->all()` оправдан

`->all()` уместен на **границе**, когда наружу нужен именно массив:

- Метод объявляет `@return list<T>`/`array<...>` (например, View-ассемблер строит
  `list<PostView>` для Response-DTO).
- Значение уходит в чужой API (SDK AWS, `Parameter` для `where in`, `sprintf`).
- Для `list<T>` из коллекции с преобразованием элемента — канонично
  `$collection->mapToList($fn)` (метод базового `TypedCollection`): он сам делает
  `\array_values($coll->toBase()->map($fn)->all())` и сразу даёт `list<TNew>` (метод
  коллекции `->all()` PHPStan видит как `array<int, T>`, не `list<T>`, поэтому ручная связка
  тянула за собой `array_values`). Ручной `\array_values(...->all())` оставляй только там,
  где `mapToList()` неприменим: спред id-списка в variadic-метод,
  `\array_values(\array_unique($list))` над плоским `list<string>`, и конвейер с
  переиндексирующей операцией между `map` и `all` (`->map($fn)->unique()->all()`) как граница
  `list<T>`. На коллекциях-картах (`<string, …>`, где ключ важен) `mapToList()` не применять —
  он сбрасывает ключи; для них `->toBase()->keyBy($fn)->map($fn)`.

`->all()` НЕ оправдан как промежуточный шаг между двумя коллекциями (это round-trip из
п. 2).

## 6. Дженерики и PHPStan (важные нюансы)

- `take()`, `slice()`, `filter()`, `values()`, `sortBy()` возвращают `static` — класс
  сохраняется (`PostCollection->take()` → `PostCollection`).
- `last()`/`first()` возвращают `TValue|null` — обращение к свойству через `?->`.
- `map()` в **другой** тип на `final`-коллекции ломает дженерик → `->toBase()` сначала
  (п. 4).
- `toBase()` → `Illuminate\Support\Collection<TKey, TValue>`, разворачивания в массив нет.
- Конструктор коллекции принимает `Arrayable<TKey, TValue>|iterable<TKey, TValue>` —
  можно передать другую `Collection` напрямую.
- `groupBy()` на типизированной коллекции даёт вложенные коллекции и путает дженерики —
  группируй через `->toBase()`, как в примере выше.

## Ключевые правила

1. Коллекция на входе и выходе → весь конвейер на коллекции, без `->all()` в середине.
2. Никаких `new SomeCollection($x->all())` — передавай `Collection`/`Arrayable` прямо,
   или используй `->toBase()`.
3. Cursor-пагинация: `->take($limit)` + `$page->count() > $limit` + `->last()?->id`.
4. Смена типа элемента → `->toBase()->map($fn)` (не `new Collection($x->all())`).
5. `list<T>` из коллекции с преобразованием элемента → `$collection->mapToList($fn)`
   (канонично, метод базового `TypedCollection`). Ручной `\array_values(...->all())` — только
   там, где `mapToList()` неприменим (variadic-спред, `array_unique`, `map(...)->unique()->all()`);
   на коллекциях-картах не применять.
6. `foreach` — только ради побочных эффектов; чистые трансформации — методы коллекции.
7. Не подменяй методы коллекции на `array_filter`/`array_map`/`array_slice`/`\count`.
