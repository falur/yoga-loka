---
title: Ревью слоя БД модулей Posts и Tags (второй круг доводки, черновик)
date: 2026-06-17 21:53
target: >-
  app/src/Modules/Posts/, app/src/Modules/Tags/,
  app/src/Shared/Domain/ValueObject/TagId.php,
  app/database/migrations/20260617.160942_0_create_posts_domain_tables.php,
  tests/Unit/Modules/Posts/, tests/Unit/Modules/Tags/,
  tests/Feature/Modules/Posts/, tests/Feature/Modules/Tags/
plan:
  - docs/plans/2026-06-17_14-16_posts-comments-db-layer.md
  - docs/plans/2026-06-17_19-02_extract-tags-module.md
mode: strict
score: 95
status: final
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet), codex]
---

# Ревью: слой БД модулей Posts и Tags (второй круг доводки, черновик)

## Оценка

**95/100.** Это финальное состояние после обоих планов и второго круга доводки по
прошлому ревью. Обязательное замечание прошлого круга закрыто корректно и с запасом:
`Post::create()` теперь держит инвариант взаимоисключающего вложения через приватный
guard `assertAttachmentIsConsistent()` (исчерпывающий `match` по `AttachmentType` без
`default`), а в unit-тестах добавлены и негативные сочетания (`Lesson`+none,
`Practice`+none, `None`+заполненный lesson, `Media`+заполненная practice, lesson и
practice одновременно → `InvalidDomainValueException`), и позитивные. Два принятых из
прошлого ревью optional на месте: комментарий-обоснование намеренного запаса ширины
`tags.text(64)` против `TagText(50)` есть и в миграции, и в Entity `Tag`; feature-тест
гидрации связи `Post HasMany PostTag` добавлен (`testPostHasManyTagsHydratesFromDatabase`).

Новый проход по репозиториям, VO, счётчикам, typecast-классам, миграции и тестам не
выявил ни одной проблемы, требующей обязательного исправления. Мета-ревью этого круга
добавило один необязательный пункт качества — дублирование тестовой фикстуры
`createPostFor()` в четырёх feature-тестах `Posts` (см. «Замечания»); это `−1` к оценке
относительно черновика, блокером не является. Код консистентен с
обоими планами и каноном проекта (read-only репозитории на `AbstractRepository`,
типизированный курсор по UUID v7 через `when()`/`WhenSelect`, общий `ValueObjectCast` по
соглашению + отдельные typecast только для nullable↔null-object и дат, доменные guard'ы в
`Comment::delete()`/`PostBlock::markUnblocked()`). Три ранее осознанно отклонённых
optional (обобщение 4 счётчиков, сужение `\Throwable`, обобщение 12 typecast'ов) в этом
круге не воспроизводятся: новых аргументов за них нет.

## Проблемы сверки с планом

Явных проблем по плану не найдено. Набор сущностей (10), VO, enum, коллекций (10),
typecast'ов (12), репозиториев и таблиц (10) совпадает с контрактами обоих планов;
порядок `down()` в миграции (`comment_mentions`, `comment_likes`, `comments`,
`post_blocks`, `post_tags`, `post_mentions`, `post_likes`, `post_media`, `tags`, `posts`)
совпадает с единственным списком из «Данные и БД» плана 1; оба self-FK
(`parent_post_id`, `parent_comment_id`) сделаны отдельным `->update()` с
`indexCreate => false`, как предписано. По плану 2 теги вынесены в `App\Modules\Tags`,
`TagId` перенесён в `App\Shared\Domain\ValueObject`, а в `Posts` от тегов осталась только
связь `PostTag` и её типы — это намеренный результат, а не недоделка. Обязательное
расхождение прошлого круга (инвариант вложения в `create()`) закрыто.

## Замечания

### 1. Дублирование тестовой фикстуры `createPostFor()` (quality, на усмотрение автора)

В четырёх feature-тестах модуля `Posts` приватный метод `createPostFor(UserId): Post`
повторяется почти дословно:

- `tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:228`
- `tests/Feature/Modules/Posts/Repository/LikesAndMentionsRepositoryTest.php:150`
- `tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php:89`
- `tests/Feature/Modules/Posts/Repository/PostBlockRepositoryTest.php:134`

Тела трёх первых байт-в-байт идентичны (`Post::create(... status: PostStatus::Published
...)`), четвёртое отличается единственным аргументом (`PostStatus::Blocked`). Все четыре
теста наследуют общий базовый `PostsRepositoryTestCase`, где такой фикстуре и место.
Вынос `protected function createPost(UserId $userId, PostStatus $status =
PostStatus::Published): Post` в базовый класс убрал бы три дубля без потери изоляции
(`PostBlockRepositoryTest` передавал бы `status: PostStatus::Blocked`). Риска бага сейчас
нет, но при изменении сигнатуры `Post::create()` (например, добавление обязательного
параметра) править придётся 4+ места — это реальная стоимость сопровождения.

Важно: это НЕ то же самое, что осознанно принятое в плане 2 дублирование
`createUser()`/`persist()` между `PostsRepositoryTestCase` и `TagsRepositoryTestCase`
(межмодульная изоляция тест-инфраструктуры) — здесь дублирование внутримодульное,
внутри одного базового тест-кейса, и устраняется без связывания модулей.

### Проверенные и отклонённые гипотезы

В ходе сверки рассматривалась гипотеза о неверном сравнении с `NULL` в
`PostBlockRepository::findActiveByPostId()` (`->where('unblocked_at', '=', null)`).
Проверкой отклонена как ложная: query builder Cycle транслирует это в `IS NULL`, что
подтверждено зелёным feature-тестом `testFindActiveReturnsNullWhenUnblocked` (обе ветки:
активный блок при `unblocked_at IS NULL` и `null` после разблокировки), и тот же приём
уже используется в `NotificationRepository`/`NotificationBulkWriter`. Замечанием не
является — зафиксировано здесь только чтобы следующий ревьюер не поднимал его повторно.

## Рекомендации

- **Править обязательно:** нет.
- **На усмотрение автора:** вынести `createPostFor()` в `PostsRepositoryTestCase` как
  `createPost(UserId, PostStatus = Published)` и убрать три дубля в feature-тестах
  `Posts` (Замечание 1).

## Изменения после мета-ревью

Режим `strict`, `review.include_code_quality: true`. Мета-ревьюеры (sonnet):
`architecture-check`, `rules-check`, `plan-check` (оба плана), `quality-check`.

### После architecture-check
- **+ Добавлено:** нет — архитектурных проблем не выявлено (границы модулей, слои,
  read-only репозитории, typecast по соглашению/отдельный класс, внутримодульные
  relations, размещение исключений, единая история миграций — всё соответствует).
- **~ Изменено:** нет.
- **− Убрано:** нет.
- **Отклонено:** нет предложений.

### После rules-check
- **+ Добавлено:** нет — нарушений `rules.md` не выявлено (`declare(strict_types=1)`,
  строгие сравнения, trailing commas, именованные аргументы, `match` без `default`,
  Entity без примитивов, `InvalidDomainValueException` из VO, UUID v7, отсутствие
  `assert()`/`switch`/мёртвого кода, русский язык текстов, 100% покрытие методов).
- **~ Изменено:** нет.
- **− Убрано:** нет.
- **Отклонено:** нет предложений.

### После plan-check
- **+ Добавлено:** нет — оба плана выполнены полностью. Подтверждено: 10 таблиц с
  колонками/индексами/FK, оба self-FK отдельным `->update()` с `indexCreate => false`,
  порядок `down()`, набор VO/enum/коллекций/12 typecast/репозиториев, типизированный
  курсор `PostId|null`/`CommentId|null`, `findByTexts(...)` вариадик,
  `findReplies(CommentId)`, guard'ы `Comment::delete()`/`PostBlock::markUnblocked()`,
  инвариант `Post::create()` через `assertAttachmentIsConsistent()` (исчерпывающий
  `match`). План 2: `TagId` в `Shared`, словарь тегов в `Tags`, в `Posts` только
  `PostTag`, импорты обновлены, тесты разнесены по модулям. Два принятых optional
  прошлого круга на месте (комментарий `tags.text(64)`, `testPostHasManyTagsHydrates...`).
- **~ Изменено:** нет.
- **− Убрано:** нет.
- **Отклонено:** нет предложений.

### После quality-check
- **+ Добавлено:** Замечание 1 — дублирование `createPostFor()` в четырёх feature-тестах
  `Posts`; тип `quality`, рекомендация «на усмотрение автора». Реальная (не межмодульная)
  внутримодульная фикстура-дубликат, устранимая выносом в базовый `PostsRepositoryTestCase`.
- **~ Изменено:** раздел «Замечания» переструктурирован (новый подзаголовок для
  проверенных/отклонённых гипотез про `IS NULL`).
- **− Убрано:** нет.
- **Отклонено:**
  - «`findByTexts()` с нулём аргументов даёт `IN ()` — добавить guard или тест».
    Отклонено: реализация байт-в-байт повторяет принятый прецедент проекта
    `RoleRepository::findByIds(RoleId ...$ids)` и `PermissionRepository::findByIds(...)`
    (тот же `where('id', 'in', new Parameter(array_map(...)))`); вводить отдельную
    политику для `TagRepository` — рассогласование с проектом. Утверждение «Cycle строит
    `WHERE text IN ()`» не подтверждено: `Parameter([])` обрабатывается ORM, а не уходит
    сырым `IN ()`. Нового аргумента, специфичного для тегов, нет.
  - «`TagCollectionTest` проверяет только пустую коллекцию — пробел покрытия `map`/`filter`».
    Отклонено: `TagCollection` не переопределяет `map`/`filter` (как и все коллекции
    проекта на `Illuminate\Support\Collection`), эти методы — vendor-код вне покрытия;
    план 2 предписал для `TagCollection` именно проверку «пустая/count», что совпадает с
    тем, как `PostsCollectionTest` проверяет join-коллекции; гейт 100% покрытия зелёный —
    реального пробела нет.

### После соседнего CLI (codex)
- Кросс-CLI запущен (`codex exec`, полный отчёт — в
  `docs/reviews/2026-06-17_21-53_posts-tags-db-layer_cli_meta.md`).
- **+ Добавлено:** нет — Codex не нашёл пропущенных проблем в коде, архитектуре,
  планах или тестах.
- **− Убрано:** нет — Codex подтвердил, что пункт про `createPostFor()` соответствует
  коду и не совпадает с отклонённой межмодульной темой `createUser()`/`persist()`.
- **~ Изменено:** по замечанию Codex переформулирован сам этот служебный блок —
  кросс-CLI фактически доступен и выполнен (ранее ошибочно помечался пропущенным).
- **Отклонено / не применимо:** `?` Codex про невозможность локально прогнать
  `make phpstan`/`make test` — ограничение его песочницы (нет доступа к Docker API),
  а не замечание к ревью; зелёный гейт `make qa` (PHPStan level max + покрытие
  100.00%) уже зафиксирован в журналах исполнения.
