---
date: 2026-06-22 17:34
source: text (вызов eda-fix с фрагментом GetUserFeedHandler)
status: done
---

# Фикс: коллекции без round-trip «коллекция → массив → коллекция»

## Контекст

Вход — фрагмент `GetUserFeedHandler`, где результат репозитория разворачивался в массив
(`->all()`), обрабатывался array-функциями (`\count()`, `array_slice()`, индексирование) и
снова заворачивался в `new PostCollection(...)`. Задача от пользователя:

1. исправить логику в этом обработчике;
2. добавить правило и скил, который учит работать с коллекциями Laravel;
3. проверить и исправить такие же места по всему коду — «не используем коллекции на всю
   мощь, гоняем туда-сюда».

Учтены рамки проекта: `AGENTS.md`, `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md`.
Ключевые правила: «Collection-пайплайны», «Не подменять методы коллекции array-функциями»,
«Типизированные Laravel Collections», «Cursor-пагинация по UUID v7 `id`», «Нет мёртвого
кода», «Инлайн одноразовых переменных». Опорная каноничная идиома уже была в коде
(`S3MediaFileService`, `MediaMultipartPartCollection`): `->toBase()->map()->values()->all()`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php` | Cursor-пагинация на методах коллекции: `->take($limit)` + `$page->count() > $limit` + `$visible->last()?->id->value()`; убран импорт `PostCollection` | Убрать round-trip `all → count → array_slice → new PostCollection` |
| 2 | `app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php` | То же; убран импорт `CommentCollection` | Тот же антипаттерн |
| 3 | `app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php` | То же; убран импорт `CommentCollection` | Тот же антипаттерн |
| 4 | `app/src/Modules/Notifications/Application/Query/Notification/ListNotifications/ListNotificationsHandler.php` | То же, `notifications: $visible` напрямую; убран импорт `NotificationCollection` | Тот же антипаттерн |
| 5 | `app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php` | `new Collection($userSessions->all())` → `$userSessions->toBase()`; убран импорт `Illuminate\Support\Collection` | Убрать ручной unwrap+rewrap в базовый Collection |
| 6 | `app/src/Modules/Auth/Application/Query/GetUserSessions/GetUserSessionsHandler.php` | `new Collection($tokens->all())` → `$tokens->toBase()`; `new AuthTokenCollection($sessionTokens->all())` → `new AuthTokenCollection($sessionTokens)`; результат `->values()` передаётся в `new AuthSessionCollection(...)` без `->all()` | Убрать двойной round-trip, сохранив группировку через базовый Collection |
| 7 | `app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php` | `$users->map()->all()` + два `@var`-костыля → `findByIds(...)->toBase()->map(...)` прямо в конструктор `UserPublicProfileCollection`; убран импорт `UserCollection` | Убрать round-trip и ручные override типов |
| 8 | `docs/rules.md` | Расширено правило про коллекции: запрет round-trip, идиома cursor-пагинации, `toBase()->map()` для смены типа элемента; ссылка на новый скил | Зафиксировать класс ошибки на уровне правил |
| 9 | `.claude/skills/laravel-collections/SKILL.md` | Новый скил-справочник по работе с Illuminate Collection в проекте | Обучение: конвейеры, round-trip, cursor-пагинация, toBase, дженерики под PHPStan |

### Канонический вид фикса (все 4 cursor-пагинатора)

```php
$page = $repo->find(..., limit: $query->limit + 1);   // уже типизированная коллекция
$visible = $page->take($query->limit);                // тот же класс коллекции
return new Result(
    items: $assembler->from($visible, $viewer),       // или $visible напрямую
    nextCursor: $page->count() > $query->limit ? $visible->last()?->id->value() : null,
);
```

## Места, осознанно НЕ тронутые (легитимный `->all()` на границе, а не round-trip)

| Файл | Почему оставлено |
|------|------------------|
| `Notifications/.../NotificationController.php`, `NotificationSettingController.php` | `\array_values(\array_map($fn, $coll->all()))` отдаёт наружу `list<Resource>` со сменой типа элемента; `\array_values` нужен PHPStan для `list<T>`. Это разрешённая правилом граница, а не промежуточный шаг между коллекциями. |
| `Notifications/.../SendPushNotificationHandler.php` | Уже есть комментарий, объясняющий `array_map` поверх `->all()` (map типизированной коллекции сохраняет generic элемента). |
| `Posts/.../PostViewAssembler.php`, `CommentViewAssembler.php` | Один `->all()` в начале для пакетной сборки (батч-выборки авторов/медиа/тегов без N+1). Не round-trip — массив используется как `list<T>` для построения view. |
| `Posts/.../PostContentComposer.php::notifyPostMentions` | `->map()->values()->all()` отдаёт `list<string>` — правильный конвейер, заканчивающийся `->all()` для нужного наружу типа. |
| `Media/.../S3MediaFileService.php`, `MediaMultipartPartCollection.php` | Уже используют каноничную идиому `->toBase()->map()->values()->all()`. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | `[OK] No errors`, level max, дженерики `take/last/count/toBase/map/values` резолвятся без larastan |
| `make test` | ✓ | `OK (1203 tests, 3944 assertions)` |

Поведение cursor-пагинации уже покрыто feature-тестами (`testPaginatesWithCursor`,
`testEmptyFeedReturnsEmptyData` в `GetUserFeedHttpTest` и аналоги в комментариях/инбоксе),
сессии — `AuthController` session-тестами, профили — листингами ленты/комментариев.
Изменения behavior-preserving (рефакторинг чтения), поэтому новые тесты не добавлялись.

## Раунд 2: устранение дублирования (по фидбесу «копай глубже»)

Первый раунд убрал round-trip, но оставил одну и ту же cursor-механику скопированной в 4
обработчика, а также дублирование cursor-SQL в 6 методах репозиториев. Вынес в два общих
примитива.

| # | Файл | Что |
|---|------|-----|
| 10 | `app/src/Shared/Domain/Pagination/CursorSlice.php` (новый) | Дженерик-примитив: `fromOverfetched(overfetched, limit, cursorOf)` → `{items, nextCursor}`. Сохраняет конкретный тип коллекции через `@template TCollection of Collection<int, TValue>` |
| 11 | `app/src/Shared/Infrastructure/Cycle/WhenSelect.php` | Добавлен `cursorById($cursor, $limit)` — `id DESC` + `where('id','<',$cursor)` при курсоре + `limit`, последнее звено `select()` |
| 12 | `GetUserFeedHandler`, `GetPostCommentsHandler`, `GetCommentRepliesHandler`, `ListNotificationsHandler` | Переведены на `CursorSlice::fromOverfetched(...)`; ручной `take/count/last` убран |
| 13 | `PostRepository` (2 метода), `CommentRepository` (3), `NotificationRepository` (1) | Cursor-блок `when(cursor)+orderBy('id','DESC')+limit` заменён на `->cursorById(...)`; в `CommentRepository`/`NotificationRepository` убран ставший лишним импорт `WhenSelect` |
| 14 | `tests/Unit/Shared/Domain/Pagination/CursorSliceTest.php` (новый) | Unit-тест `CursorSlice`: overfetch/ровно лимит/меньше лимита/пусто (покрытие 100%) |
| 15 | `docs/rules.md`, `.claude/skills/laravel-collections/SKILL.md` | Правило и скил обновлены: указывают на `cursorById` и `CursorSlice`, а не на ручную идиому |

Итог обработчика после раунда 2:

```php
$slice = CursorSlice::fromOverfetched(
    overfetched: $this->commentRepository->findReplies(parentId: $parent->id, cursor: $cursor, limit: $query->limit + 1),
    limit: $query->limit,
    cursorOf: static fn(Comment $comment): string => $comment->id->value(),
);
return new GetCommentRepliesResult(
    replies: $this->commentViewAssembler->fromComments(comments: $slice->items, viewer: $viewer),
    nextCursor: $slice->nextCursor,
);
```

Итог репозитория:

```php
return new CommentCollection(
    $this->select()
        ->where('parent_comment_id', $parentId->value())
        ->where('deleted_at', '=', null)
        ->cursorById(cursor: $cursor?->value(), limit: $limit)
        ->fetchAll(),
);
```

## Проверки (финальные, после раунда 2)

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | `[OK] No errors`; дженерик `CursorSlice` сохраняет конкретный тип коллекции на стороне ассемблеров |
| `make test` | ✓ | `OK (1207 tests, 3952 assertions)` (+4 unit-теста `CursorSlice`) |
| `make test-coverage` | ✓ | `Покрытие 100.00% соответствует порогу 100.00%` |

## Открытые вопросы

Нет.
