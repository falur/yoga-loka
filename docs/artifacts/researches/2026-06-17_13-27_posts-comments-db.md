---
title: Модуль записей (постов) и комментариев — слой базы данных
date: 2026-06-17 13:27
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль записей (постов) и комментариев — слой базы данных

## Суть

Нужен слой базы данных для нового модуля записей (постов) и комментариев в
модульном монолите YogaLoka (PHP 8.5, Spiral, Cycle ORM, PostgreSQL). На этом
шаге проектируем только таблицы, сущности, value object, enum и связи Cycle ORM —
без HTTP, Application-сценариев и доставки уведомлений.

Записи несут текст и вложения (медиа, либо одно занятие, либо одну практику),
теги, упоминания пользователей, лайки, репосты и статус (черновик / опубликовано /
заблокировано). К записям пишут древовидные комментарии с мягким удалением,
упоминаниями и лайками. Блокировки записей — отдельная таблица-история по образцу
`user_bans`. Уведомления про упоминания/комментарии/лайки расширяют существующий
модуль `Notifications` через его реестр видов — на схему БД записей это не влияет.

Проблема: модуля записей в коде ещё нет, а связанные модули занятий и практик не
созданы, поэтому ссылки на них надо заложить так, чтобы не блокировать разработку
сейчас и достроить целостность позже.

## Решение

### Название модуля

Модуль называем `Posts` (`App\Modules\Posts`), сущности — `Post`, `Comment`,
`Tag`, `PostLike`, `CommentLike`, `PostBlock` и т.д. В `arch.md:23` как пример
области приведён `Feed`, но пользователь явно ждёт **отдельный социальный модуль**
в будущем (лента/таймлайн «друг добавил запись»), и имя `Feed` логично оставить за
ним — это про *доставку и показ* ленты, а наш модуль про *контент*. Если решите
иначе, меняется только namespace, схема таблиц (`posts`, `comments`, `post_*`,
`comment_*`, `tags`) от имени модуля не зависит — имена таблиц в проекте без
модульного префикса (`media`, `users`, `user_bans`; `arch.md:660-669`).

### Карта таблиц

```text
                              ┌──────────────────────────────┐
   users (FK) ◄───── user_id  │           posts              │  parent_post_id ──► posts (self, репост)
   media (FK) ◄── post_media  │  id, user_id, text, status,  │
                              │  attachment_type, lesson_id, │  lesson_id / practice_id — без FK
                              │  practice_id, parent_post_id,│  (таблиц занятий/практик ещё нет)
                              │  likes_count, reposts_count, │
                              │  comments_count, deleted_at  │
                              └───┬───────┬──────┬──────┬─────┘
                  ┌───────────────┘       │      │      └────────────────┐
            post_media               post_likes  post_tags          post_mentions
         (media_id ►media,          (user_id    (tag_id ►tags)      (user_id ►users)
          position, уник.            ►users,     post_tags M:N        отметки в записи
          post_id+media_id)          уник.)      tags◄──┐
                                                         │
   post_blocks ──► posts            tags (словарь: text уник., created_by_id ►users)
   (история блокировок,
    зеркало user_bans)

                              ┌──────────────────────────────┐
   posts (FK) ◄───── post_id  │          comments            │  parent_comment_id ──► comments (self, дерево)
   users (FK) ◄───── user_id  │  id, post_id, user_id, text, │
                              │  parent_comment_id,          │  deleted_at / deleted_by_id / deletion_reason
                              │  likes_count, deleted_at,    │
                              │  deleted_by_id, deletion_*   │
                              └───┬───────────────┬──────────┘
                       comment_likes        comment_mentions
                    (user_id ►users,      (user_id ►users,
                     уник. comment+user)   отметки в комментарии)
```

### Таблицы по полям

`posts` — записи:

| Колонка | Тип | Примечание |
|---|---|---|
| id | uuid PK | UUID v7 (`rules.md:36`) |
| user_id | uuid, FK→users RESTRICT | автор |
| text | text, nullable | null-object VO `PostText`; запись может быть без текста (только вложение) |
| status | string(32) | enum `PostStatus { Draft, Published, Blocked }` |
| attachment_type | string(16) | enum `AttachmentType { None, Media, Lesson, Practice }` — дискриминатор взаимоисключающих вложений |
| lesson_id | uuid, nullable, без FK | ссылка на занятие; FK добавим миграцией, когда появится модуль |
| practice_id | uuid, nullable, без FK | ссылка на практику; FK добавим позже |
| parent_post_id | uuid, nullable, FK→posts SET NULL | исходная запись для репоста |
| likes_count | integer, default 0 | денормализованный счётчик |
| reposts_count | integer, default 0 | денормализованный счётчик (предлагаю, симметрично лайкам) |
| comments_count | integer, default 0 | денормализованный счётчик (предлагаю, нужен для ленты и не дороже лайков) |
| deleted_at | datetime, nullable | мягкое удаление (как `users.deleted_at`) |
| created_at, updated_at | datetime | `HasTimestamps` |

Индексы: PK(id) — глобальная лента и cursor-пагинация по UUID v7 (`rules.md:37`);
`(user_id, id)` — лента автора; `(status)`; `(parent_post_id)` — поиск репостов.

`post_media` — вложения-медиа (много на запись):

| Колонка | Тип | Примечание |
|---|---|---|
| id | uuid PK | |
| post_id | uuid, FK→posts CASCADE | |
| media_id | uuid, FK→media RESTRICT | нельзя удалить медиа, пока на него ссылаются |
| position | integer | порядок показа |
| created_at, updated_at | datetime | |

Уникальный `(post_id, media_id)`, индекс `(post_id, position)`.

`post_likes` / `post_mentions` — обе по образцу join-таблицы:

| Колонка | Тип |
|---|---|
| id | uuid PK |
| post_id | uuid, FK→posts CASCADE |
| user_id | uuid, FK→users CASCADE |
| created_at, updated_at | datetime |

Уникальный `(post_id, user_id)`, индекс `(user_id)` — «мои лайки» / «где меня
отметили».

`tags` — словарь тегов:

| Колонка | Тип | Примечание |
|---|---|---|
| id | uuid PK | |
| text | string(64) | уникальный, нормализованный |
| created_by_id | uuid, FK→users RESTRICT | кем создан |
| created_at, updated_at | datetime | |

`post_tags` — связь записей и тегов: `id` PK, `post_id` FK→posts CASCADE, `tag_id`
FK→tags RESTRICT, уникальный `(post_id, tag_id)`, индекс `(tag_id)` — записи по
тегу.

`post_blocks` — блокировки записей (зеркало `user_bans`,
`create_user_domain_tables.php:42-61`):

| Колонка | Тип |
|---|---|
| id | uuid PK |
| post_id | uuid, FK→posts CASCADE |
| reason | string(500) |
| blocked_by_id | uuid, FK→users RESTRICT |
| unblocked_at | datetime, nullable |
| unblocked_by_id | uuid, nullable, FK→users SET NULL |
| unblock_reason | string(500), nullable |
| created_at, updated_at | datetime |

Активная блокировка (`unblocked_at IS NULL`) держит `posts.status = Blocked` —
ровно тот же приём, что `user_bans` + `UserStatus::Banned`
(`User.php:174-184`). Запись-факт хранится в `post_blocks`, оперативный статус — в
`posts.status`.

`comments` — комментарии (дерево + мягкое удаление):

| Колонка | Тип | Примечание |
|---|---|---|
| id | uuid PK | |
| post_id | uuid, FK→posts CASCADE | |
| user_id | uuid, FK→users RESTRICT | автор |
| text | text | обязателен |
| parent_comment_id | uuid, nullable, FK→comments SET NULL | дерево ответов |
| likes_count | integer, default 0 | лайки комментариев включены |
| deleted_at | datetime, nullable | когда удалён |
| deleted_by_id | uuid, nullable, FK→users SET NULL | кем удалён (автор или админ) |
| deletion_reason | string(500), nullable | причина (если удалён из админки) |
| created_at, updated_at | datetime | |

Индексы: `(post_id, id)` — список комментариев записи cursor-пагинацией;
`(parent_comment_id)` — ответы; `(user_id)`.

`comment_likes` / `comment_mentions` — точные аналоги `post_likes`/
`post_mentions`, но с `comment_id` FK→comments CASCADE; уникальный
`(comment_id, user_id)`, индекс `(user_id)`.

### Ключевые развилки и почему так

| Развилка | Выбор | Почему |
|---|---|---|
| Вложения медиа/занятие/практика | взаимоисключающе: медиа (много) **или** 1 занятие **или** 1 практика | ответ пользователя; дискриминатор `attachment_type` делает инвариант явным и запросимым, инвариант стережёт домен |
| Кросс-модульные FK | FK на существующие `users` и `media` | ответ пользователя; повторяет свежий прецедент `User` (`users.avatar_media_id → media`, `create_user_domain_tables.php:34-39`); на занятия/практики FK нет, пока нет таблиц |
| Ссылки на занятие/практику сейчас | `lesson_id` / `practice_id` — uuid-колонки без FK | модулей ещё нет; тот же приём, что `recipient_id` без FK в `Notifications`, когда таблицы не было (notifications_module_design `:155-159`); FK добавим миграцией позже |
| Счётчик лайков | денормализованное поле `likes_count` на записи и комментарии | ответ пользователя; лента без `COUNT`, обновляется доменным методом в одной транзакции с лайком |
| Родительская запись | репост: `parent_post_id` → исходная запись | ответ пользователя; обсуждение закрывают отдельные комментарии, поэтому это репост, а не ответ |
| Лайки комментариев | включаем сейчас (`comment_likes` + `likes_count`) | ответ пользователя |
| Статус и удаление | enum `Draft/Published/Blocked` + мягкое `deleted_at` | ответ пользователя; `deleted_at` как у `users`, блокировка — статус + история `post_blocks` |
| Связи Cycle ORM | внутримодульные — `HasMany`/`BelongsTo`; кросс-модульные — uuid-колонка + свой VO, без relation | внутри модуля — как `Media → конверсии` (`Media.php:82-98`); между модулями relation нарушил бы границы (`arch.md:140-146`), целостность держит FK в миграции |

### Доменная модель и typecast

- **Идентификаторы** — отдельные VO на UUID v7 (`PostId`, `CommentId`, `TagId`,
  `PostLikeId`, …) поверх общего `AbstractUuidV7Id`, как `UserId`
  (`Shared/Domain/ValueObject/UserId.php`). Гидрация — общий `ValueObjectCast` по
  соглашению (`rules.md:91`).
- **Автор** — общий `App\Shared\Domain\ValueObject\UserId` (он в `Shared`, его
  можно тянуть из любого модуля).
- **Ссылки на чужие сущности** (`media_id`, `lesson_id`, `practice_id`) — это VO
  **своего** модуля (`Posts/Domain/ValueObject`), а не импорт `MediaId` из домена
  Media: домен зависит только от своего домена и `Shared/Domain`
  (`arch.md:264`). Ровно так User завёл свой `UserAvatar` поверх
  `avatar_media_id`, не импортируя `MediaId` (`User.php:62-63`).
- **Текст записи** — null-object VO `PostText` (nullable-колонка на non-null VO →
  отдельный typecast-класс в `Posts/Infrastructure/Cycle`, как `UserBioTypecast`;
  `rules.md:91`). Текст комментария — обязательный VO `CommentText`.
- **Enum** `PostStatus`, `AttachmentType`, и при необходимости `MentionSource` —
  по `rules.md:35`; исчерпывающий `match` без `default`.
- **Удаление комментария** — null-object VO `CommentDeletion`, инкапсулирующий
  тройку `deleted_at / deleted_by_id / deletion_reason` (отдельный typecast),
  чтобы не протаскивать `?T` через границы (`rules.md:22`).
- **Счётчики** — VO `LikesCount` / `RepostsCount` / `CommentsCount` с методами
  `increment()/decrement()` (как `MediaProcessingAttempts`), а не голый `int`
  (`rules.md:41`).
- **Коллекции связей** — именованные типизированные коллекции
  (`PostMediaCollection`, `PostTagCollection`, `CommentCollection`, …;
  `rules.md:21`).

### Предлагаемые ограничения значений

| Значение | Ограничение | Обоснование |
|---|---|---|
| Текст записи | до 5000 символов, `trim`, пусто допустимо при наличии вложения | «возможность писать посты» = длинный текст; число настраиваемое |
| Текст комментария | 1..2000 символов, непустой после `trim` | короче записи |
| Текст тега | 1..50 символов, нижний регистр (`mb_strtolower`), без пробелов; буквы (вкл. кириллицу), цифры, `_`, `-`; уникальный | сообщество русскоязычное — теги могут быть кириллицей |
| position вложения | целое ≥ 0 | порядок медиа |

Числа — предложение, поправьте при необходимости; они влияют только на валидацию
в VO, не на структуру таблиц.

### Расширение уведомлений (вне схемы БД записей)

Виды уведомлений — это классы `NotificationTypeDefinition`, которые модуль
регистрирует в своём бутлоадере через `NotificationTypeRegistryContract`, а
отправка идёт прямым вызовом `NotificationSenderContract::send()` внутри
`#[Transactional]`-Handler-а записи (notifications_module_design `:80-112`,
`NotificationSenderContract.php`, `NotificationSender.php`). Заводим по решению
пользователя четыре вида в `Posts/Application`:

```text
posts.post_mention     — упомянули в записи
posts.comment_mention  — упомянули в комментарии
posts.post_commented   — прокомментировали вашу запись
posts.post_reaction    — лайк/репост вашей записи
```

На схему БД записей это не влияет: данные для уведомления (кто кого упомянул,
кто лайкнул) уже лежат в `post_mentions` / `comment_mentions` / `post_likes` /
`parent_post_id`. Сами уведомления хранит модуль `Notifications`.

## Ответы на вопросы

| Вопрос | Ответ пользователя | Как учтено |
|---|---|---|
| Вложения медиа/занятие/практика | Взаимоисключающе | `attachment_type` + `post_media` (много медиа) либо `lesson_id` либо `practice_id`; инвариант в домене |
| Кросс-модульные FK | FK на users и media | FK на `users`/`media`; занятие/практика — uuid без FK до появления модулей |
| Счётчик лайков | Хранить `likes_count` | денормализованный счётчик на записи и комментарии |
| Смысл «родительской записи» | Репост / поделиться | `parent_post_id` → исходная запись (репост), не ответ |
| Лайки на комментарии | Включить сейчас | таблица `comment_likes` + `comments.likes_count` |
| Виды уведомлений | Все четыре | `post_mention`, `comment_mention`, `post_commented`, `post_reaction` (лайк/репост) |
| Статусы и удаление записи | Draft/Published/Blocked + `deleted_at` | enum статусов + мягкое удаление + история `post_blocks` |

Принято мной как следствие выбранного направления и правил проекта (без отдельного
вопроса):

- Имя модуля `Posts` (имя `Feed` оставлено будущему модулю ленты).
- Дополнительно к `likes_count` предлагаю денормализованные `reposts_count` и
  `comments_count` на записи — то же решение про кеш-счётчики, нужны ленте.
- Дискриминатор `attachment_type` для явного взаимоисключающего вложения.
- Правила удаления FK: контентные дети — CASCADE (`post_media`, `post_likes`,
  `post_tags`, `post_mentions`, `comments`, `comment_*`), авторство/словарь —
  RESTRICT (`posts.user_id`, `comments.user_id`, `tags`), мягкие ссылки —
  SET NULL (`parent_post_id`, `parent_comment_id`, `deleted_by_id`,
  `unblocked_by_id`).

## Итог

Делаем слой БД модуля `Posts` из таблиц: `posts`, `post_media`, `post_likes`,
`post_tags`, `tags`, `post_mentions`, `post_blocks`, `comments`, `comment_likes`,
`comment_mentions` — единой миграцией в стиле `create_*_domain_tables`
(UUID v7 PK, snake_case, явные индексы и FK). Подход:

1. **Вложения** — взаимоисключающе через `attachment_type`: медиа во многих
   `post_media` (FK→media), либо одно занятие/практика в `lesson_id`/`practice_id`
   (uuid без FK, FK добавим позже). Инвариант стережёт домен.
2. **Кросс-модульные ссылки** — FK на существующие `users` и `media`; занятие и
   практика — пока без FK. Связи между модулями выражены в коде через свои VO, а
   не Cycle relation; внутримодульные связи — `HasMany`/`BelongsTo`.
3. **Счётчики** — денормализованные `likes_count` (записи и комментарии),
   предлагаемые `reposts_count` и `comments_count` — обновляются доменным методом
   в одной транзакции с событием.
4. **Репост** — `parent_post_id` → исходная запись. **Комментарии** — дерево по
   `parent_comment_id`, мягкое удаление тройкой `deleted_at/deleted_by_id/
   deletion_reason`.
5. **Блокировки** — статус `Blocked` на записи + история `post_blocks` (зеркало
   `user_bans`).
6. **Уведомления** — четыре вида регистрируются в реестре `Notifications` из
   `Posts/Application`; на схему БД записей не влияют.

Вне области (кандидаты в `docs/roadmaps/posts.md`, каждый обсудить отдельно — файл
создаём на шаге реализации, т.к. это исследование пишет только в
`docs/researches/`):

- уведомление «друг добавил запись» — для будущего социального модуля (лента);
- заглушение конкретной записи/ветки (per-source mute) — как в Notifications
  вынесено за рамки;
- история редактирования записей и комментариев;
- полнотекстовый поиск по записям и тегам;
- FK на занятия и практики — добавить миграцией, когда появятся их таблицы.

Следующий шаг — `eda-plan` по этому отчёту: пошаговый план домена, миграции,
typecast-слоя, репозиториев и регистрации видов уведомлений.
