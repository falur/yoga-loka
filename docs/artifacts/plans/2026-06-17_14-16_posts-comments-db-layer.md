---
title: Модуль Posts — слой базы данных (сущности, миграция, репозитории, VO)
date: 2026-06-17 14:16
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-17_13-27_posts-comments-db.md
---

# План реализации

## Задача

Реализовать **только слой базы данных** нового модуля `App\Modules\Posts` (записи и
комментарии): доменные сущности Cycle ORM, value object и enum, typecast-слой,
типизированные коллекции, одну миграцию на 10 таблиц и read-only репозитории с
доменными методами.

Вне области этого плана (подтверждено пользователем — «слой БД»): HTTP-слой
(контроллеры, фильтры, ресурсы), Application-сценарии (Command/Query/Handler),
регистрация видов уведомлений и их доставка, bootloader модуля.

Готово, когда: миграция накатывается и откатывается в Docker; все сущности
сохраняются и восстанавливаются через `EntityManager`/репозитории с корректной
гидрацией VO; CASCADE/RESTRICT/SET NULL и unique-индексы проверены тестами;
`make test` и `make phpstan` зелёные; покрытие 100%.

## Контекст

Зачем: модуля записей в коде ещё нет, а лента, комментарии, лайки, репосты,
теги и упоминания — ядро социальной сети. Слой БД закладывается первым, чтобы
поверх него строить сценарии. Связанные модули занятий и практик ещё не созданы,
поэтому ссылки на них закладываются без FK, чтобы не блокировать разработку и
достроить целостность позже отдельной миграцией.

Эталоны в коде, которым план следует буквально:

- **Миграции** — `app/database/migrations/20260613.143901_0_create_user_domain_tables.php`
  и `20260613.143902_0_create_access_domain_tables.php`: голый
  `Cycle\Migrations\Migration`, `protected const DATABASE = null`, fluent
  `addColumn/addForeignKey/addIndex/setPrimaryKeys`, составной unique
  `addIndex(['a','b'], ['unique' => true])`, FK с
  `['delete' => ..., 'update' => 'CASCADE', 'indexCreate' => false]`. Имя файла —
  `YYYYMMDD.HHmmss_0_create_posts_domain_tables.php`. Запуск: `php app.php migrate`
  в Docker.
- **Родительская сущность со связями** — `Media.php` (`HasMany` к
  `MediaImageConversion` с `collection:` и `orderBy`).
- **Дочерняя/join-сущность** — `MediaImageConversion.php` (FK-колонка как VO +
  `BelongsTo` назад) и `RolePermission.php`/`UserRole.php` (плоская join-сущность:
  только FK-колонки как VO, без relation, без timestamps, своя `create()` и свой
  репозиторий).
- **Многоколоночное опциональное состояние** — `UserBan.php`: тройка
  `unbanned_at/unbanned_by_id/unbanned_reason` смоделирована **тремя
  одноколоночными null-object VO** (`BanUnbannedAt::notUnbanned()`,
  `BanUnbannedBy::none()`, `BanUnbannedReason::none()`), каждый со своим typecast,
  и обновляется одним доменным методом `markUnbanned(...)`.
- **VO-эталоны** — `AbstractUuidV7Id` (id), `AbstractIntegerValue` (счётчик с
  диапазоном), `Email`/`UserBio` (текст с валидацией), `UserBio`+`UserBioTypecast`
  (nullable-колонка → non-null null-object VO), `UserAvatar`+`UserAvatarTypecast`
  (ссылка на чужой модуль без импорта `MediaId`), `MediaExpiration`+
  `MediaExpirationTypecast` (nullable datetime), `MediaProcessingAttempts`
  (счётчик с `increment()`).
- **Typecast** — общий `Shared/Infrastructure/Cycle/ValueObjectCast` по
  соглашению для non-nullable VO (`fromString`/`fromInt`/`BackedEnum::from` →
  `value()`); отдельный `*Typecast implements ColumnValueTypecast` в
  `Modules/{Module}/Infrastructure/Cycle` только для nullable↔null-object и
  datetime.
- **Репозиторий** — `extends App\Shared\Infrastructure\Cycle\AbstractRepository`
  (даёт `when()` через `WhenSelect`), read-only доменные методы поверх
  `findByPK/findOne/select()`, PHPDoc `@extends AbstractRepository<Entity>`.
- **Коллекция** — `final class XCollection extends Illuminate\Support\Collection`
  с PHPDoc `@extends Collection<int, X>`.
- **Тесты** — VO/enum/typecast: `tests/Unit` на `PHPUnit\Framework\TestCase`;
  сущности+репозитории: `tests/Feature` на `Tests\DatabaseTestCase`
  (`persist()`/`run()`/`cleanOrmHeap()`, всё в откатываемой транзакции).

Ключевой факт обнаружения сущностей: Cycle подхватывает классы с `#[Entity]`
автоматически через Tokenizer/`AnnotatedBootloader` (`app/config/cycle.php`,
`Kernel`). **Для слоя БД bootloader модулю не нужен** — сущности и репозитории
резолвятся фреймворком без явной регистрации (как `Media`/`User`/`Access`).
`PostsBootloader` появится только вместе с Application-слоем (биндинги контрактов,
регистрация видов уведомлений и outbox-Job) — это вне области плана. Пустой
bootloader сейчас не заводим (правило «нет мёртвого кода»).

## Принятые решения

Источник большинства решений — research с подтверждёнными ответами пользователя;
здесь только то, что зафиксировано или принято в рамках `recommend_and_ask`.

- **Имя модуля `Posts`** (`App\Modules\Posts`) — подтверждено пользователем. Имя
  `Feed` остаётся будущему модулю ленты.
- **Три денормализованных счётчика** на `posts`: `likes_count`, `reposts_count`,
  `comments_count` — подтверждено пользователем. На `comments` — `likes_count` и
  `replies_count`.
- **Счётчик прямых ответов `replies_count` на `comments`** — по запросу
  пользователя. Денормализованное число прямых дочерних комментариев (не всех
  потомков), чтобы показывать «N ответов» в дереве без `COUNT`; обновляется
  доменным методом `Comment::incrementReplies()/decrementReplies()` родителя в
  одной транзакции с добавлением/удалением ответа. По аналогии со счётчиками
  записи.
- **Кросс-модульные ссылки** (`media_id`, `lesson_id`, `practice_id`,
  `parent_post_id`) выражаются **своими VO модуля Posts**, не импортом
  `MediaId`/`PostId` чужого домена; `user_id` — общий `Shared\Domain\ValueObject\UserId`
  (он в `Shared`, разрешён везде). Подтверждено research; следует правилу
  `arch.md:263` (Domain зависит только от своего Domain и Shared/Domain).
- **FK только на существующие `users` и `media`**; на занятия/практики FK нет
  (таблиц ещё нет) — подтверждено пользователем. `lesson_id`/`practice_id` —
  nullable uuid-колонки без FK.
- **Удаление FK**: контентные дети — CASCADE; авторство/словарь/медиа — RESTRICT;
  мягкие ссылки — SET NULL (полный список в «Данные и БД»). Принято автором
  research; следует прецеденту `user_bans`/`reserved_nicknames`. CASCADE на
  `post_likes.user_id`/`comment_likes.user_id` безопасен для денормализованных
  счётчиков: пользователи в проекте удаляются мягко (`UserStatus::Deleted` +
  `UserDeletion`, `User::markDeleted`, hard-delete нет), поэтому каскад строк
  лайков не срабатывает и счётчики не разъезжаются — тот же приём, что
  `user_bans.user_id` CASCADE. Если появится hard-delete пользователя, счётчики
  придётся пересчитывать отдельной операцией (вне слоя БД).
- **Многоколоночные композиты — НЕ одним VO и НЕ через `Embeddable`.** Адаптация
  рекомендации research: удаление комментария (`deleted_at`/`deleted_by_id`/
  `deletion_reason`) и разблокировка записи (`unblocked_at`/`unblocked_by_id`/
  `unblock_reason`) моделируются **набором одноколоночных null-object VO** по
  образцу `UserBan` (`BanUnbannedAt`/`BanUnbannedBy`/`BanUnbannedReason`),
  объединённых доменным методом. Причина (`decision_mode: autonomous` для способа
  ORM-маппинга): в проекте `Embeddable` и многоколоночный композит-VO не
  применяются ни разу (проверено grep); Cycle здесь маппит одна колонка ↔ одно
  VO-свойство. Схема таблиц от этого не меняется.
- **Связи Cycle ORM минимальны и осмысленны**: `Post HasMany PostMedia`
  (упорядоченно по `position`) и `Post HasMany PostTag` — ограниченные наборы,
  показываются вместе с записью. Лайки, упоминания, комментарии, блокировки —
  не eager-связь, а доменные методы репозиториев, возвращающие типизированные
  коллекции (наборы не ограничены сверху / пагинируются). `parent_post_id` и
  `parent_comment_id` — null-object VO-колонки, не self-relation (relation не
  выражает «нет родителя» без `?T`).
- **Репозитории — на `AbstractRepository`** (текущий канон с `when()`), а не на
  голом `Cycle\ORM\Select\Repository`.
- **Ограничения значений VO** (из research, влияют только на валидацию, не на
  схему): текст записи ≤ 5000, `trim`, пусто допустимо; текст комментария
  1..2000, непустой; текст тега 1..50, нижний регистр, буквы (вкл. кириллицу)/
  цифры/`_`/`-`, уникальный; `position` ≥ 0; `reason`/`unblock_reason` блокировки
  ≤ 500. Эти числа можно поправить на этапе реализации без изменения таблиц.

Ожидаемый объём (plan_size: normal): ~10 сущностей, ~10 коллекций, ~10
репозиториев, ~36 VO, 2 enum, ~12 typecast-классов, 1 миграция (10 таблиц),
плюс unit- и feature-тесты. 5 фаз.

## Целевой алгоритм

Слой БД — это пассивная модель; «поведение» проявляется в раунд-трипе
сохранения/чтения:

1. **Создание записи.** Application (вне плана) собирает VO и вызывает
   `Post::create(author, text, status, attachment)`, который генерирует `PostId`
   (UUID v7), ставит `attachment_type`, обнуляет счётчики
   (`LikesCount/RepostsCount/CommentsCount::zero()`), `PostDeletion::notDeleted()`,
   `initializeTimestamps()`. Дочерние строки (`PostMedia`, `PostTag`, `PostLike`,
   `PostMention`) создаются своими `create()` и сохраняются тем же
   `EntityManager::run()`.
2. **Гидрация.** На чтение Cycle отдаёт сущность; non-nullable VO
   восстанавливаются общим `ValueObjectCast` по соглашению, nullable-колонки →
   null-object VO через свои `*Typecast`. Наружу `?T` не виден.
3. **Переходы состояния** — только доменными методами Entity (счётчики
   `incrementLikes()/decrementLikes()` и т.д., `publish()`, `block()/unblock()`,
   `softDelete()/restore()`, для комментария `delete(by, at, reason)/restore()`).
   Транзакцию и `run()` открывает вызывающий Handler (вне плана); сущности и
   репозитории сюда не лезут.
4. **Чтение наборов** — доменные методы репозиториев возвращают сущность или
   типизированную коллекцию; cursor-пагинация по `id` (UUID v7), `ORDER BY id DESC`.
5. **Целостность на уровне БД** держат FK и unique-индексы из миграции независимо
   от ORM-связей (в т.ч. `parent_post_id`/`parent_comment_id` SET NULL, хотя они
   не relation).

## Контракты реализации

### Данные и БД

Одна миграция `create_posts_domain_tables`, 10 таблиц, snake_case, UUID v7 PK,
`created_at/updated_at` (datetime) у всех таблиц с `HasTimestamps`. Self-FK
(`posts.parent_post_id`, `comments.parent_comment_id`) добавляются после создания
таблицы отдельным `->addForeignKey(...)->update()` в той же миграции.

**`posts`** — записи:
- `id` uuid PK; `user_id` uuid (FK→users RESTRICT); `text` text nullable;
  `status` string(32); `attachment_type` string(16); `lesson_id` uuid nullable
  (без FK); `practice_id` uuid nullable (без FK); `parent_post_id` uuid nullable
  (FK→posts SET NULL); `likes_count` integer default 0; `reposts_count` integer
  default 0; `comments_count` integer default 0; `deleted_at` datetime nullable;
  `created_at`, `updated_at` datetime.
- Индексы: PK(id); index(`user_id`, `id`); index(`status`); index(`parent_post_id`).

**`post_media`** — медиа-вложения (много на запись):
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `media_id` uuid (FK→media
  RESTRICT); `position` integer; `created_at`, `updated_at` datetime.
- unique(`post_id`, `media_id`); index(`post_id`, `position`).

**`post_likes`**:
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `user_id` uuid (FK→users
  CASCADE); `created_at`, `updated_at` datetime.
- unique(`post_id`, `user_id`); index(`user_id`).

**`post_mentions`** (та же форма, что `post_likes`):
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `user_id` uuid (FK→users
  CASCADE); `created_at`, `updated_at` datetime.
- unique(`post_id`, `user_id`); index(`user_id`).

**`tags`** — словарь:
- `id` uuid PK; `text` string(64); `created_by_id` uuid (FK→users RESTRICT);
  `created_at`, `updated_at` datetime.
- unique(`text`).

**`post_tags`** — связь записей и тегов:
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `tag_id` uuid (FK→tags
  RESTRICT); `created_at`, `updated_at` datetime.
- unique(`post_id`, `tag_id`); index(`tag_id`).

**`post_blocks`** — история блокировок (зеркало `user_bans`):
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `reason` string(500);
  `blocked_by_id` uuid (FK→users RESTRICT); `unblocked_at` datetime nullable;
  `unblocked_by_id` uuid nullable (FK→users SET NULL); `unblocked_reason`
  string(500) nullable (имена `unblocked_*` зеркалят `user_bans.unbanned_*`);
  `created_at`, `updated_at` datetime.
- index(`post_id`).

**`comments`** — комментарии (дерево + мягкое удаление):
- `id` uuid PK; `post_id` uuid (FK→posts CASCADE); `user_id` uuid (FK→users
  RESTRICT); `text` text; `parent_comment_id` uuid nullable (FK→comments SET NULL);
  `likes_count` integer default 0; `replies_count` integer default 0
  (денормализованное число прямых ответов на этот комментарий); `deleted_at`
  datetime nullable; `deleted_by_id` uuid nullable (FK→users SET NULL);
  `deletion_reason` string(500) nullable; `created_at`, `updated_at` datetime.
- index(`post_id`, `id`); index(`parent_comment_id`); index(`user_id`).

**`comment_likes`** / **`comment_mentions`** (аналоги `post_*`, но с `comment_id`
FK→comments CASCADE):
- `id` uuid PK; `comment_id` uuid (FK→comments CASCADE); `user_id` uuid (FK→users
  CASCADE); `created_at`, `updated_at` datetime.
- unique(`comment_id`, `user_id`); index(`user_id`).

`down()` дропает таблицы в обратном порядке зависимостей: `comment_mentions`,
`comment_likes`, `comments`, `post_blocks`, `post_tags`, `post_mentions`,
`post_likes`, `post_media`, `tags`, `posts`.

Совместимость со старыми данными/backfill/rollback не требуются — таблицы новые,
`down()` полностью дропает схему модуля.

### API и внешние контракты

Не затрагивается. HTTP-маршруты, фильтры, ресурсы и Application-сценарии — вне
области плана.

## Фазы выполнения

### 1. Value Object и Enum

Цель: завести доменный словарь модуля — все VO и enum, на которых держатся
сущности.

Что сделать (`app/src/Modules/Posts/Domain/ValueObject` и `.../Domain/Enum`):

- **Идентификаторы** (`final readonly class extends AbstractUuidV7Id {}`):
  `PostId`, `CommentId`, `TagId`, `PostMediaId`, `PostLikeId`, `PostMentionId`,
  `PostTagId`, `PostBlockId`, `CommentLikeId`, `CommentMentionId`. `UserId` —
  переиспользуем из `Shared`.
- **Текст с валидацией** (образец `Email`/`UserBio`): `PostText` (null-object,
  как `UserBio`: `none()` = нет текста, `fromString()` — непустой ≤5000 после
  `trim`; «пусто допустимо» означает, что Application при отсутствии текста берёт
  `none()`, а НЕ что `fromString('')` проходит — пустая строка не валидна, как у
  `UserBio`); `CommentText`
  (non-null, 1..2000, непустой); `TagText` (non-null, 1..50, `mb_strtolower`,
  только буквы вкл. кириллицу/цифры/`_`/`-`, регэксп нормализации); `BlockReason`
  (non-null, ≤500); `BlockUnblockedReason` (null-object, ≤500);
  `CommentDeletionReason` (null-object, ≤500).
- **Счётчики** (`extends AbstractIntegerValue`, `MIN=0`, фабрика `zero()`):
  `LikesCount`, `RepostsCount`, `CommentsCount`, `RepliesCount` (прямые ответы на
  комментарий), `MediaPosition` (без inc/dec). Обязательно **переопределяют
  `MAX`** — у базового класса `MAX=0`, иначе `increment()` сразу упадёт; берём
  `MAX = PHP_INT_MAX`. `increment()` есть у эталона `MediaProcessingAttempts`;
  **`decrement()` в проекте отсутствует — добавляем сами**: при значении `<= MIN`
  бросает `InvalidDomainValueException` (симметрично `increment()` на `MAX`), не
  уходит ниже нуля. Счётчики **не объявляют `fromString`** — тогда `ValueObjectCast`
  гидрирует их через `fromInt` (ветка выбирается по наличию `fromString`, см.
  `ValueObjectCast::castField`).
- **Ссылки на чужой модуль / self** (свой VO модуля Posts поверх uuid, без
  импорта чужого id): `PostMediaReference` — non-null (всегда задан, валидирует
  UUID v7 медиа), образец `MediaId`/любой id-VO с `fromString`, **без `none()`**;
  null-object (образец `UserAvatar`): `PostLesson`, `PostPractice`, `PostOriginal`
  (parent_post_id — исходная запись репоста), `CommentParent` (parent_comment_id)
  — фабрики `none()`/`pointingTo()`, `pointingTo()` валидирует UUID v7 и бросает
  `InvalidDomainValueException` при невалидном.
- **Null-object datetime/uuid для soft-delete и блокировки** (образец
  `MediaExpiration`, `BanUnbannedAt`/`BanUnbannedBy`): `PostDeletion`
  (`notDeleted()`/`deletedAt()`, nullable datetime); `CommentDeletedAt`,
  `BlockUnblockedAt` (nullable datetime); `CommentDeletedBy`, `BlockUnblockedBy`
  (nullable uuid).
- **Enum** (`string`-backed, `Domain/Enum`): `PostStatus { Draft, Published,
  Blocked }`; `AttachmentType { None, Media, Lesson, Practice }`.

Все VO: `readonly`, приватный конструктор, фабрика с валидацией, `value()`,
`equals()`, `Stringable`+`JsonSerializable` где есть строковое представление;
исключения — `InvalidDomainValueException` (500), сообщения на русском без
англицизмов.

Результат: полный набор VO и enum компилируется, PHPStan зелёный.

Сценарии тестирования (`tests/Unit/Modules/Posts/Domain/...`):
- id: `generate()` даёт UUID v7, `fromString()` отвергает v4, `equals()`/
  `jsonSerialize()`.
- тексты: граничные длины (0/1/max/max+1), `trim`, нормализация тега (верхний
  регистр → нижний, кириллица проходит, пробел/спецсимвол отвергается), пустой
  `CommentText`/`TagText` → исключение; `PostText::fromString('')`/пробельный →
  исключение (как `UserBio`), а отсутствие текста выражается отдельной фабрикой
  `PostText::none()`.
- счётчики: `zero()`, `increment()`/`decrement()`, нижняя граница 0 → исключение.
- null-object: `none()`/`notDeleted()` → `isEmpty()/isDeleted()` корректны;
  `pointingTo()` валидирует UUID v7.
- enum: набор кейсов, backing-значения.

Проверка: `make test` (suite Unit) и `make phpstan` зелёные.

### 2. Typecast-классы и типизированные коллекции

Цель: дать инфраструктуре правила гидрации nullable VO и завести коллекции —
типы возврата связей и репозиториев.

Что сделать:

- **Typecast** (`app/src/Modules/Posts/Infrastructure/Cycle`,
  `implements App\Shared\Infrastructure\Cycle\ColumnValueTypecast`, статические
  `castDatabaseValue()`/`uncastValue()`, образец `UserBioTypecast`/
  `MediaExpirationTypecast`/`BanUnbannedAtTypecast`): `PostTextTypecast`,
  `PostDeletionTypecast`, `PostLessonTypecast`, `PostPracticeTypecast`,
  `PostOriginalTypecast`, `CommentParentTypecast`, `CommentDeletedAtTypecast`,
  `CommentDeletedByTypecast`, `CommentDeletionReasonTypecast`,
  `BlockUnblockedAtTypecast`, `BlockUnblockedByTypecast`,
  `BlockUnblockedReasonTypecast`. Non-nullable VO (id, `CommentText`, `TagText`,
  `BlockReason`, счётчики, `MediaPosition`, `PostMediaReference`, enum)
  обслуживает общий `ValueObjectCast` по соглашению — для них typecast-класс НЕ
  создаём (правило «без pass-through обёрток»).
- **Коллекции** (`app/src/Modules/Posts/Domain/Collection`, `extends
  Illuminate\Support\Collection`, PHPDoc `@extends Collection<int, X>`):
  `PostCollection`, `CommentCollection`, `TagCollection`, `PostMediaCollection`,
  `PostTagCollection`, `PostLikeCollection`, `PostMentionCollection`,
  `PostBlockCollection`, `CommentLikeCollection`, `CommentMentionCollection`.

Результат: typecast-классы и коллекции готовы к подключению в сущностях.

Сценарии тестирования (`tests/Unit/Modules/Posts/Infrastructure/Cycle/...`):
- каждый typecast: `castDatabaseValue(null)` → null-object (`isEmpty()`),
  `castDatabaseValue(значение)` → заполненный VO; `uncastValue(none)` → `null`,
  `uncastValue(значение)` → скаляр/`DateTimeImmutable`; round-trip
  cast→uncast→cast эквивалентен; datetime принимает строку и `DateTimeInterface`.
- коллекции: пустая коллекция нужного класса, `map`/`filter` сохраняют тип
  элемента (быстрая проверка типобезопасности).

Проверка: `make test` (Unit) и `make phpstan` зелёные.

### 3. Сущности и связи Cycle ORM

Цель: 10 Entity, маппинг колонок на VO/enum, связи и доменные методы.

Что сделать (`app/src/Modules/Posts/Domain/Entity`, образец `User`/`Media`/
`UserBan`/`RolePermission`; `#[Entity(role, table, repository, typecast:
[Typecast::class, ValueObjectCast::class])]`, `public private(set)` VO-свойства с
`#[Column(... typecast: ...)]`, `use HasTimestamps`):

- **`Post`** — все колонки `posts` как VO/enum (`text`→`PostTextTypecast`,
  `deleted_at`→`PostDeletionTypecast`, `lesson_id`/`practice_id`/`parent_post_id`
  → свои `*Typecast`, счётчики и статус по соглашению). Связи: `#[HasMany(target:
  PostMedia, innerKey: 'id', outerKey: 'post_id', orderBy: ['position' => 'ASC'],
  collection: PostMediaCollection::class)]` и аналогичный `HasMany` к `PostTag`
  (`PostTagCollection`). Доменные методы: `create(UserId, PostText, PostStatus,
  AttachmentType, PostLesson, PostPractice, PostOriginal)` (генерит `PostId`,
  счётчики `zero()`, `PostDeletion::notDeleted()`, инициализирует коллекции и
  timestamps); `publish()`, `block()`/`unblock()` (меняют `PostStatus`),
  `softDelete()`/`restore()` (через `PostDeletion`), `incrementLikes()`/
  `decrementLikes()`/`incrementReposts()`/`decrementReposts()`/
  `incrementComments()`/`decrementComments()`, `setLesson()`/`setPractice()`/
  `setMediaAttachment()`/`clearAttachment()` (стерегут взаимоисключающий
  `attachment_type`: каждый ставит свой `AttachmentType` и обнуляет чужие ссылки
  через приватный `clearOtherAttachments()`, guard-clause +
  `InvalidDomainValueException`). На этом слое домен держит инвариант «ровно один
  из {None, Media, Lesson, Practice}» и «при Lesson/Practice заполнен ровно один
  id»; согласованность `attachment_type = Media` ↔ наличие строк `post_media`
  (отдельная таблица) — вне слоя БД, за неё отвечает Application. `touch()` в
  каждом мутаторе.
- **`Comment`** — колонки `comments`; `parent_comment_id`→`CommentParentTypecast`,
  тройка удаления — `CommentDeletedAt`/`CommentDeletedBy`/`CommentDeletionReason`
  со своими typecast (образец `UserBan`); `likes_count`, `replies_count` по
  соглашению (`LikesCount`/`RepliesCount`). Методы: `create(PostId, UserId,
  CommentText, CommentParent)` (счётчики `zero()`); `delete(CommentDeletedBy,
  CommentDeletedAt, CommentDeletionReason)`/`restore()` (guard: `CommentDeletedAt`
  и `CommentDeletedBy` обязаны быть заполнены — `delete()` с «пустой» тройкой
  бросает `InvalidDomainValueException`, чтобы не сохранить противоречивое
  состояние); `incrementLikes()`/
  `decrementLikes()`; `incrementReplies()`/`decrementReplies()` (вызывает родитель,
  когда добавлен/удалён прямой ответ); predicate `isDeleted()` (источник истины —
  `CommentDeletedAt`, по образцу `UserBan::isActive` на `unbannedAt`).
- **`Tag`** — `create(TagText, UserId $createdBy)`.
- **`PostMedia`** — плоская: `PostMediaId`, `PostId $postId`, `PostMediaReference
  $media`, `MediaPosition $position`, `HasTimestamps`; `BelongsTo` к `Post`
  (`innerKey: 'post_id'`, `outerKey: 'id'`, `fkOnDelete: 'CASCADE'`) для обратной
  навигации (образец `MediaImageConversion`); `create(Post, PostMediaReference,
  MediaPosition)`.
- **`PostLike`, `PostMention`, `PostTag`, `CommentLike`, `CommentMention`** —
  плоские join-сущности по образцу `RolePermission`/`UserRole`: только FK-колонки
  как VO + `HasTimestamps`, без relation, своя `create()`. `PostTag` хранит
  `PostId`+`TagId`; `*Mention`/`*Like` — `PostId`/`CommentId`+`UserId`.
  `HasTimestamps` здесь — осознанное отличие от `RolePermission`/`UserRole` (у них
  timestamps нет): у лайков/упоминаний/тегов время создания осмысленно («мои
  лайки» по времени, лента), и research включает `created_at`/`updated_at`.
- **`PostBlock`** — зеркало `UserBan`: `PostBlockId`, `PostId`, `BlockReason`,
  `UserId $blockedBy`, тройка разблокировки (`BlockUnblockedAt`/`BlockUnblockedBy`/
  `BlockUnblockedReason` со своими typecast), `HasTimestamps`; методы
  `create(PostId, BlockReason, UserId $blockedBy)`, `markUnblocked(BlockUnblockedBy,
  BlockUnblockedAt, BlockUnblockedReason)` (guard: `BlockUnblockedAt` и
  `BlockUnblockedBy` обязаны быть заполнены, иначе `InvalidDomainValueException`),
  predicate `isActive()`.

Результат: сущности маппятся на схему, статически валидны.

Сценарии тестирования (`tests/Unit/Modules/Posts/Domain/Entity/...`, чистые, без
БД): `create()` ставит UUID v7, дефолтные счётчики/статус/null-object и
timestamps; счётчики inc/dec меняют значение; `publish/block/unblock`, `softDelete/
restore`, `delete/restore` комментария меняют состояние и `touch()` обновляет
`updatedAt`; взаимоисключающий `attachment_type` — установка `Lesson` сбрасывает
`Practice`, конфликт → исключение; `PostBlock::markUnblocked` → `isActive()` false.

Проверка: `make test` (Unit) и `make phpstan` зелёные.

### 4. Миграция

Цель: создать схему БД под сущности.

Что сделать: `app/database/migrations/YYYYMMDD.HHmmss_0_create_posts_domain_tables.php`
(`class CreatePostsDomainTables extends Cycle\Migrations\Migration`,
`protected const DATABASE = null`). В `up()` — 10 таблиц из раздела «Данные и БД»
в порядке: `tags`, `posts` (затем self-FK `parent_post_id` через
`->table('posts')->addForeignKey(...)->update()`), `post_media`, `post_likes`,
`post_mentions`, `post_tags`, `post_blocks`, `comments` (затем self-FK
`parent_comment_id` через `->update()`), `comment_likes`, `comment_mentions`.
Все FK — `['delete' => <RESTRICT|CASCADE|SET NULL>, 'update' => 'CASCADE',
'indexCreate' => false]`; индексы и unique — как в «Данные и БД». `down()` дропает
в порядке, явно перечисленном в конце раздела «Данные и БД» (единственный источник
истины этого порядка). Имя файла: реальный timestamp на момент реализации (после
последней миграции `20260616.180010`).

Внимание: техника self-FK через `->table('posts')->addForeignKey(...)->update()`
(после `->create()`) в проекте ещё не применялась (все 8 существующих миграций
без self-FK; `->update()` для ALTER уже используется в
`20260616.180010_0_add_device_to_auth_tokens.php`). Поэтому: индекс
`index(parent_post_id)`/`index(parent_comment_id)` добавляется при `->create()`, а
self-FK — отдельным `->update()` с `'indexCreate' => false` (иначе дубль индекса);
эту механику проверяем накаткой/откатом до написания остального (см. «Проверка»).

Результат: `php app.php migrate` создаёт все таблицы, `migrate:rollback`
полностью откатывает.

Сценарии тестирования: ОБЯЗАТЕЛЬНАЯ ранняя приёмка — `migrate` создаёт все
таблицы, индексы, unique и оба self-FK без ошибок, `migrate:rollback` дропает без
висящих FK. Этого прецедента (self-FK через `->update()`) в проекте нет, поэтому
прогон наката/отката делается до написания остального кода слоя, а НЕ откладывается
«до фазы 5». Отдельный PHPUnit-тест на миграцию не пишем (существующие модули его
не имеют) — приёмка миграции ручная в Docker; корректность схемы дополнительно
подтверждают feature-тесты фазы 5.

Проверка (в Docker, ПЕРВЫМ делом в фазе — раньше остального кода миграции, чтобы
проверить непроверенную в проекте self-FK технику): `php app.php migrate` и
`php app.php migrate:rollback` проходят без ошибок; затем `php app.php cache:clean`
(чтобы новые `#[Entity]` попали в схему Cycle) и повторный `migrate` для фазы 5.

### 5. Репозитории и интеграционные тесты

Цель: read-only репозитории с доменными методами и сквозная проверка раунд-трипа
на реальной БД.

Что сделать (`app/src/Modules/Posts/Repository`, `extends AbstractRepository`,
PHPDoc `@extends AbstractRepository<Entity>`, только чтение — без `save/persist/
delete/update` и без транзакций; образец `MediaRepository`):

- `PostRepository`: `findById(PostId): ?Post`, `findByUserId(UserId $userId,
  PostStatus|null $status, PostId|null $cursor, int $limit): PostCollection`
  (типизированный курсор и `when()` строго по образцу
  `NotificationRepository::findPageForRecipient`: `$cursor?->value()`, named-args
  `when(condition: ..., callback: ...)`, `where('id', '<', $cursorId)`,
  `ORDER BY id DESC`; `status = null` — без фильтра, `cursor = null` — первая
  страница; репозиторий не прячет soft-deleted неявно — это решает вызывающий),
  `findRepostsOf(PostId): PostCollection`.
- `CommentRepository`: `findById(CommentId): ?Comment`, `findByPostId(PostId
  $postId, CommentId|null $cursor, int $limit): CommentCollection`,
  `findReplies(CommentId $parentId, CommentId|null $cursor, int $limit):
  CommentCollection` (родителя принимаем как `CommentId`, не null-object — критерий
  запроса всегда конкретен; курсор типизирован, без сырой строки).
- `TagRepository`: `findById(TagId): ?Tag`, `findByText(TagText): ?Tag`,
  `findByTexts(TagText ...$tagTexts): TagCollection` (вариадик вместо сырого
  `list` — критерий запроса типизирован).
- `PostMediaRepository`: `findByPostId(PostId): PostMediaCollection`.
- `PostLikeRepository`: `findByPostAndUser(PostId, UserId): ?PostLike`,
  `existsByPostAndUser(...): bool`, `findByUserId(UserId): PostLikeCollection`.
- `PostMentionRepository`: `findByPostId(PostId): PostMentionCollection`,
  `findByUserId(UserId): PostMentionCollection`.
- `PostTagRepository`: `findByPostId(PostId): PostTagCollection`,
  `findByTagId(TagId): PostTagCollection`.
- `PostBlockRepository`: `findById(PostBlockId)`, `findActiveByPostId(PostId):
  ?PostBlock` (`unblocked_at IS NULL`).
- `CommentLikeRepository`: `findByCommentAndUser(CommentId, UserId): ?CommentLike`,
  `existsByCommentAndUser(...): bool`.
- `CommentMentionRepository`: `findByCommentId(CommentId): CommentMentionCollection`.

Методы строят выборку через `$this->select()`/`findOne`/`findByPK`; коллекции
возвращаются обёрнутыми в свои `*Collection`; bool — для проверок существования.

Результат: весь слой БД работает end-to-end.

Сценарии тестирования (`tests/Feature/Modules/Posts/Repository/...`, `extends
Tests\DatabaseTestCase`, `persist()`/`run()`/`cleanOrmHeap()`). Каждый тест
сначала создаёт и `persist()`-ит реальные строки `User` и `Media` (общий
fixture-helper по образцу `MediaRepositoryTest::createMedia`/
`UserRepositoryTest::createUser`), и все дочерние строки ссылаются на их реальные
id — иначе голые `UserId::generate()`/`PostMediaReference::pointingTo()` упадут на
FK. Любая проверка FK-поведения (CASCADE/RESTRICT/SET NULL) выполняется после
`cleanOrmHeap()` и перечитывания из БД, а не на объектах из памяти:
- round-trip `Post` со всеми VO (текст, счётчики, статус, attachment, soft-delete)
  и c пустыми null-object (`text=none`, `lesson/practice/parent=none`,
  `deleted_at=null`) — гидрация корректна.
- round-trip счётчика с **ненулевым** значением (`incrementLikes` ×N → persist →
  `cleanOrmHeap` → читаем N) — ловит ошибку int↔string в гидрации `fromInt`;
  декремент в 0 даёт `InvalidDomainValueException`.
- `Post HasMany PostMedia` (порядок по `position`) и `PostTag` — коллекции нужных
  классов, нужная длина; lazy-load `PostMedia->post` (`BelongsTo`).
- дерево комментариев: `parent_comment_id`, `findReplies`; `replies_count`
  родителя растёт при добавлении прямого ответа и убывает при удалении; мягкое
  удаление `delete()`→ восстановление полей `deleted_by/at/reason`, `findByPostId`.
- unique: второй `PostLike` той же пары `(post_id, user_id)` → исключение БД;
  то же для `post_media (post_id, media_id)`, `post_tags (post_id, tag_id)`,
  `tags (text)`, `comment_likes (comment_id, user_id)`.
- CASCADE: удаление `Post` удаляет `post_media/post_likes/post_mentions/post_tags/
  post_blocks/comments`; удаление `Comment` удаляет `comment_likes/comment_mentions`.
- RESTRICT: попытка удалить `media`/`user`/`tag`, на который ссылаются, → ошибка БД.
- SET NULL: удаление родительской записи репоста/родительского комментария/
  разблокировавшего обнуляет ссылку, строка остаётся. Важно: ссылки —
  null-object VO-колонки без relation, поэтому после `delete(parent)` обязателен
  `cleanOrmHeap()` и перечитывание ребёнка, иначе тест проверит stale-объект.
- `PostBlockRepository::findActiveByPostId`: возвращает блок при `unblocked_at
  IS NULL` и `null`, когда запись разблокирована (`unblocked_at IS NOT NULL`) —
  обе ветки.
- `exists*`-методы (`existsByPostAndUser`, `existsByCommentAndUser`) — обе ветки
  (true при наличии лайка, false при отсутствии).
- cursor-пагинация (`findByUserId`, `findByPostId`, `findReplies`) по `id` DESC:
  первая страница (`cursor=null`), следующая (с курсором), последняя (элементов
  меньше `limit`) и пустой результат — порядок и срез ожидаемые.

Проверка: `make test` (Unit+Feature) и `make phpstan` зелёные; покрытие 100%
по новому коду.

## Тесты

Стратегия (`after_each_phase`): каждая фаза завершается тестами и зелёными
`make test`/`make phpstan` до перехода к следующей. Фазы 1–3 — чистые unit-тесты
(`tests/Unit`, `PHPUnit\Framework\TestCase`) для VO, enum, typecast и доменного
поведения сущностей без БД. Фаза 4 проверяется накаткой/откатом миграции в Docker.
Фаза 5 — feature-тесты (`tests/Feature`, `Tests\DatabaseTestCase`, всё в
откатываемой транзакции) на гидрацию, связи, индексы, FK-поведение и пагинацию.
Требование проекта — 100% покрытие; каждый VO, typecast, доменный метод и метод
репозитория покрыт. HTTP-роутов в этом плане нет, поэтому интеграционных
route-тестов не требуется.

## Логирование

Стратегия (`debug_precise`): на слой БД **не добавляется логирования**, и это
осознанно. VO чистые (бросают `InvalidDomainValueException`), сущности только
меняют состояние, репозитории только читают — по правилам проекта (`rules.md`:
репозиторий read-only без сервис-зависимостей; Domain не зависит от инфраструктуры;
«Логирование: DEBUG по умолчанию» относится к бизнес-логике в Handler-ах).
Точное debug-логирование бизнес-операций (создание записи, лайк, блокировка,
упоминание) появится в Application Handler-ах — это следующий слой, вне области
плана. Внедрять логгер в VO/сущности/репозитории здесь было бы нарушением границ
слоёв, поэтому раздел осознанно пуст для слоя БД.

## Документация и эксплуатация

- После добавления сущностей сбросить кэш схемы Cycle в dev (`CYCLE_SCHEMA_CACHE`):
  `php app.php cache:clean` (или эквивалент), чтобы новые `#[Entity]` попали в
  схему; в тестах схема собирается заново автоматически.
- Накатить миграцию в dev/CI: `php app.php migrate` в Docker.
- `roadmap`-кандидаты из research (НЕ в этом плане, каждый — отдельной задачей):
  FK на занятия/практики после появления их таблиц; полнотекстовый поиск; история
  редактирования; per-source mute; уведомление «друг добавил запись».
- Регистрация видов уведомлений (`posts.post_mention`, `posts.comment_mention`,
  `posts.post_commented`, `posts.post_reaction`) и `PostsBootloader` — следующий
  шаг (Application-слой), вне области плана.
- `post_media.media_id` → `media` RESTRICT добавляет новую причину, по которой
  удаление медиа может быть запрещено (раньше блокировал только
  `users.avatar_media_id`). Модуль `Media` про `Posts` не знает (граница
  модулей), поэтому будущему сценарию `Media/Application/DeleteMedia` нужен
  осмысленный класс отказа (409/403) вместо «сырой» 500 от БД — задача для
  Application-слоя, фиксируем здесь как известное следствие.
- AGENTS.md/arch.md правок не требуют: модуль следует существующей структуре;
  глобальные миграции и автоскан сущностей уже описаны.

## Изменения после мета-ревью

### После моделей

Мета-ревью: haiku (быстрая), sonnet (кодовая, сверка с реальными файлами), opus
(флагман, чтение движка `ValueObjectCast`, миграций и тестов). Оценки до правок:
82 / 79 / 82.

- **+ Добавлено:**
  - Счётчики обязаны переопределять `MAX` (у `AbstractIntegerValue` `MAX=0`) и
    объявлять `decrement()` (в проекте его нет): декремент на `MIN` →
    `InvalidDomainValueException`, без ухода ниже нуля.
  - Соглашение «счётчики не объявляют `fromString`», чтобы `ValueObjectCast`
    гидрировал их через `fromInt` (тонкость `castField`).
  - Явная проверка self-FK техники (`->addForeignKey(...)->update()`) накаткой/
    откатом в Docker первым делом в фазе 4 + `'indexCreate' => false` для self-FK;
    шаг `cache:clean` после миграции.
  - Тесты: round-trip ненулевого счётчика; SET NULL с `cleanOrmHeap()` и
    перечитыванием ребёнка; обе ветки `exists*` и `findActiveByPostId`;
    cursor-пагинация (первая/следующая/последняя/пустая страницы).
  - Заметка в эксплуатации: RESTRICT на `post_media.media_id` добавит будущему
    `DeleteMedia` новый класс отказа (вне слоя БД).
- **~ Изменено:**
  - `PostText` приведён к форме `UserBio` без противоречия: `none()` = нет текста,
    `fromString()` строго непустой ≤5000; «пусто допустимо» решается на границе
    Application выбором `none()`.
  - Имена методов вложений согласованы: `setLesson()/setPractice()/
    setMediaAttachment()/clearAttachment()` + приватный `clearOtherAttachments()`;
    добавлено, что cross-table-инвариант Media↔`post_media` — вне слоя БД.
  - `PostRepostOf` → `PostOriginal` (читаемость); `PostMediaReference` — non-null
    без `none()` (образец id-VO, не `UserAvatar`).
  - Репозитории: типизированные сигнатуры пагинации (`?string $cursor`,
    `int $limit`); `findReplies(CommentId $parentId)` вместо null-object-критерия.
  - `Comment::isDeleted()` — источник истины `CommentDeletedAt`.
  - Колонки блокировки `unblocked_at/unblocked_by_id/unblocked_reason` (зеркало
    `user_bans.unbanned_*`).
- **− Убрано:** ничего (схема таблиц и подтверждённые решения не менялись).
- **Отклонено:**
  - «Рассинхрон default 0 ↔ `zero()`» — подтверждено флагманом как НЕ проблема
    (прецедент `Media.processing_attempts`): вставка идёт через Entity со
    значением 0, default не задействуется. Добавлен только тест ненулевого
    счётчика, без изменения модели.
  - Расхождение порядка `down()` между разделами — фактически порядок один;
    вместо дублирования сделана ссылка на единственный список в «Данные и БД».

## Реакция на ревью

Кросс-CLI ревью соседней CLI (`codex exec`, оценка 84/100, блокеров нет) — полный
текст в `docs/plans/2026-06-17_14-16_posts-comments-db-layer_review.md`.

Внесено в план (бесспорное):

- Типизированный курсор `PostId|null`/`CommentId|null` вместо сырого `?string` —
  строго по образцу `NotificationRepository::findPageForRecipient(UserId,
  NotificationId|null $cursor, int $limit)` (`$cursor?->value()`, named-args
  `when(condition:, callback:)`); сырая строка обходила бы валидацию UUID v7.
- `PostRepository::findByUserId` получил явный `PostStatus|null $status`,
  согласованный с обещанным фильтром `when()`; добавлено, что репозиторий не
  прячет soft-deleted неявно.
- `TagRepository::findByTexts(TagText ...$tagTexts)` вместо сырого `list`.
- Доменные guard-ы: `Comment::delete()` и `PostBlock::markUnblocked()` требуют
  заполненных даты и пользователя, иначе `InvalidDomainValueException` (нельзя
  сохранить противоречивую «пустую» тройку).
- Feature-тесты создают реальные строки `User`/`Media` (fixture-helper), иначе FK
  упадут; все проверки CASCADE/RESTRICT/SET NULL — после `cleanOrmHeap()` и
  перечитывания из БД.
- Уточнён тест `PostText`: `fromString('')` бросает, отсутствие текста — `none()`.
- Ранняя ручная приёмка миграции self-FK (`migrate`/`migrate:rollback`) сделана
  обязательной до написания остального кода, а не отложенной «до фазы 5».
- Документировано: CASCADE на `*_likes.user_id` безопасен из-за мягкого удаления
  пользователей; `HasTimestamps` на join-таблицах — осознанное отличие от
  `RolePermission` (время создания лайков/упоминаний осмысленно).

Отклонено (с причиной):

- **«Добавить индексы на FK-колонки `post_media.media_id`, `tags.created_by_id`,
  `post_blocks.blocked_by_id`, `post_blocks.unblocked_by_id`,
  `comments.deleted_by_id`».** Отклонено для консистентности с проектом:
  существующие миграции намеренно НЕ индексируют аналогичные RESTRICT/SET NULL
  ссылки — `user_bans.banned_by_id`, `user_bans.unbanned_by_id`,
  `users.avatar_media_id` идут без отдельного индекса (проверено в
  `20260613.143901_0_create_user_domain_tables.php`). Вводить для `Posts` иную
  политику индексации, чем для `User`/`Media`, — рассогласование. Стоимость
  проверки FK на этих низконагруженных ссылках проект уже принял; при появлении
  реального горячего запроса индекс добавим отдельной миграцией.
- **«Заменить CASCADE на RESTRICT для `*_likes.user_id`».** Отклонено: проект
  удаляет пользователей мягко (hard-delete нет), CASCADE повторяет прецедент
  `user_bans.user_id`; обоснование зафиксировано в «Принятые решения».

## Прогресс выполнения
Журнал: `docs/executions/2026-06-17_15-41_posts-comments-db-layer.md`

- [x] Фаза 1: Value Object и Enum
- [x] Фаза 2: Typecast-классы и типизированные коллекции
- [x] Фаза 3: Сущности и связи Cycle ORM
- [x] Фаза 4: Миграция (ранняя приёмка self-FK в Docker)
- [x] Фаза 5: Репозитории и интеграционные тесты (репозитории созданы в фазе 3, здесь — feature-тесты)
