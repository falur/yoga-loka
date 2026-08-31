Reading additional input from stdin...
OpenAI Codex v0.139.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
model: gpt-5.5
provider: openai
approval: never
sandbox: read-only
reasoning effort: high
reasoning summaries: none
session id: 019ed55b-f449-7af0-982b-3fe485b0733d
--------
user
Прочитай docs/plans/2026-06-17_14-16_posts-comments-db-layer.md, docs/rules.md, docs/arch.md, связанное исследование из sources.research при наличии (docs/researches/2026-06-17_13-27_posts-comments-db.md). Это план ТОЛЬКО слоя БД нового модуля App\Modules\Posts (PHP 8.5, Spiral, Cycle ORM, PostgreSQL): value object, enum, typecast, типизированные коллекции, сущности Cycle ORM, одна миграция на 10 таблиц, read-only репозитории и тесты. HTTP/Application/bootloader/уведомления — осознанно вне области. Затем глубоко проверь план по релевантному коду: открой файлы, модули и тесты, которые следуют из плана (эталоны app/src/Modules/Media, User, Access; app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php, AbstractRepository.php; app/database/migrations/*; tests/DatabaseTestCase.php и tests/Feature/**), и оцени фактическую реализуемость. Критическое ревью на русском: пропущенные шаги, неучтённые зависимости, нарушения docs/rules.md и docs/arch.md, скрытые риски, риски для смежного кода, недостающие тесты, шаги без критериев готовности. Каждый пункт строкой формата '- [тип] описание — что предлагаешь', где тип = блокер|важное|мелочь. В конце — оценка готовности 0-100 и 1-3 ключевых риска.
2026-06-17T11:33:56.806656Z ERROR codex_core_skills::loader: failed to stat skills path /Users/gian_tiaga/.codex/.tmp/plugins/plugins/wix/skills/wix-app/references/dashboard-menu-plugin/blog-slots.md: No such file or directory (os error 2)
codex
Сначала читаю сам план, правила и архитектуру, потом пройду по связанным файлам кода и тестов. Править ничего не буду: задача выглядит как проверка реализуемости и рисков.
exec
/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-17_14-16_posts-comments-db-layer.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
---
title: Модуль Posts — слой базы данных (сущности, миграция, репозитории, VO)
date: 2026-06-17 14:16
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
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
  research; следует прецеденту `user_bans`/`reserved_nicknames`.
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


 succeeded in 0ms:
# Правила проекта

## Язык

- Отвечай всегда на русском
- Исключения пишутся на русском языке.
- Логи пишутся на русском языке.
- Комментарии к коду пишутся на русском языке.
- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
- **Без англицизмов**: в русском тексте — комментариях, докстрингах, сообщениях логов и исключений, документации — не использовать кальки с английского, если есть обычное русское слово. Например: «временный» вместо «транзиентный», «рассылка»/«отправка» вместо «диспатч», «по умолчанию» вместо «дефолтный», «полезные данные» вместо «payload». Имена классов, методов, переменных, типов и прочие технические идентификаторы остаются на английском (`DispatchNotificationJob`, `isTransient()`, `outbox`) — переводу подлежит только человекочитаемый русский текст.
- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.

## Качество кода

- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
- **Явные типы вместо `null`**: `null` в доменной модели не протаскивается через границы — тип свойства Entity, параметры `create()`, аргументы и возвраты доменных методов не должны быть `?T` для доменных данных. Отсутствие, неизвестность или особое состояние выражается отдельным value object, enum или доменным типом, а не `?string`/`?Ip`. Форма null-object VO выбирается по содержанию состояний:
  - **Одно опциональное значение, поведение сводится к «есть/нет»** — один `readonly`-VO с приватным nullable внутри и фабриками присутствия/отсутствия (образец `MediaExpiration` — `permanent()`/`temporaryUntil()`; `MediaProcessingError` — `none()`). Такой инкапсулированный nullable правилу не противоречит: наружу `?T` не виден.
  - **Несколько состояний с разными данными или разным поведением**, где полиморфизм убирает `if`/`instanceof` у вызывающих — абстракция + реализации (образец `Ip` → `KnownIp`/`UnknownIp`).

  По умолчанию для одного опционального значения берётся форма «один VO»; абстракция с наследниками заводится только когда варианты реально несут разные данные или поведение.
- **Строгая типизация**: все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.
- **Строгие сравнения**: значения сравниваются только через `===` и `!==`; loose comparisons `==`, `!=` и `<>` запрещены.
- **Trailing commas**: обязательны в каждом многострочном списке (аргументы, массивы, `match`, параметры).
- **Нет мёртвого кода**: неиспользуемые переменные, закомментированные блоки, недостижимые ветки — удалять.
- **Инлайн одноразовых переменных**: если переменная используется один раз и получается просто (обращение к свойству, вызов метода, enum-значение), не заводить для неё отдельную переменную — подставлять выражение напрямую. `$slug = $role->slug->value; ... 'slug' => $slug` → `'slug' => $role->slug->value`.
- **Не дублировать конструктор фабриками без смысла**: статическая фабрика (`create()`, `fromParts()`, `fromItems()` и т.д.) добавляется только если она выражает отдельный доменный сценарий, преобразует внешний формат, делает дополнительную валидацию/нормализацию или скрывает сложное создание. Если метод принимает те же данные, что и конструктор, и просто вызывает `new self(...)` без нового смысла — он запрещён; используй конструктор напрямую.
- **Исключения, а не коды ошибок**: при ошибке — типизированное исключение, не `null`/`false`/код.
- **Не плодить технические константы**: одноразовые технические строки, шаблоны и сообщения оставлять рядом с использованием. Константу, enum или value object использовать, когда значение переиспользуется, является публичным контрактом или закрытым набором вариантов.
- **Enum вместо строк**: если значение имеет ограниченный набор вариантов (статус, тип, роль, категория) — использовать `enum`. Строковые литералы `'active'`, `'super_user'`, `'pending'` в бизнес-логике — красный флаг, должен быть enum.
- **UUID v7 для всех идентификаторов**: все первичные ключи и идентификаторы сущностей — UUID версии 7 (`Ramsey\Uuid\Uuid::uuid7()`). UUID v4 и автоинкремент запрещены. UUID v7 обеспечивает хронологическую сортируемость и лучшую производительность индексов в PostgreSQL.
- **Cursor-пагинация по UUID v7 `id`**: для cursor-based пагинации используется `id` (UUID v7) вместо `createdAt`. UUID v7 содержит timestamp и хронологически сортируем, поэтому `ORDER BY id DESC` эквивалентен `ORDER BY createdAt DESC`, но использует PK-индекс напрямую — без дополнительного индекса и без проблем с дубликатами timestamp.
- **Конкретные имена переменных**: запрещены абстрактные имена вроде `$handler`, `$service`, `$manager`, `$data`, `$result`, `$item`. Имя должно отражать суть: `$loginSuperUser` вместо `$handler`, `$tenantRepository` вместо `$repository`, `$activeUsers` вместо `$result`. Это касается и параметров контроллеров: `$updateUserProfileHandler` вместо `$updateHandler`, `$getUserProfileHandler` вместо `$profileHandler`, `$loginFilter` вместо `$filter`. Исключение — лямбды с очевидным контекстом (`fn(Role $role) => $role->slug`).
- **Property hooks вместо геттеров/сеттеров**: в Entity использовать PHP 8.4 property hooks (`get`/`set`) вместо методов `getX()`/`setX()`. Вычисляемые значения — через `get` hook, валидация при записи — через `set` hook.
- **Entity: фабричный метод `create()` вместо конструктора**: Entity не объявляет конструктор, если он пустой. Создание новой сущности — через статический метод `create(...)`, который принимает обязательные бизнес-параметры, инициализирует все поля и возвращает готовый объект. Это чётко разделяет «создание нового» от «восстановления из БД».
- **Entity без примитивов**: Entity не хранит и не принимает `string`, `int`, `float`, `bool`, `array` или голый `Collection` для доменных данных. Свойства Entity, аргументы `create()`, аргументы доменных методов и значения, которые Entity возвращает наружу, должны быть VO, enum, другой доменной моделью или именованной типизированной коллекцией. Исключение: `bool` разрешён только как return type у чистых predicate-методов без побочных эффектов (`isActive()`, `canLogin()`), если метод отвечает на вопрос и не протаскивает состояние наружу.
- **ValueObject для доменных примитивов**: email, пароль, slug, subdomain, имена, идентификаторы, счётчики, флаги состояния и другие одиночные значения внутри Entity — не голые примитивы, а `readonly class` в `Modules/{Module}/Domain/ValueObject` или enum для закрытого набора вариантов. Общие базовые VO и общие идентификаторы, которые не принадлежат одному модулю, лежат в `Shared/Domain/ValueObject`. Простой скалярный VO: `readonly`, `Stringable`, `JsonSerializable`, приватный конструктор, фабричный метод с валидацией, метод для чтения значения (`value()`, `toString()` или другой явный доменный метод) и `equals()`. Составной VO для JSON-значения может не быть `Stringable`, если у него нет естественного безопасного строкового представления; при этом он всё равно должен быть `readonly`, `JsonSerializable`, создаваться через фабрику, валидировать входные значения и иметь `equals()`. Вспомогательные payload/DTO для JSON не кладём в `Domain/ValueObject`, если они сами не являются полноценными VO. VO не реализует инфраструктурные интерфейсы и не зависит от Cycle ORM. Гидрация и запись VO в БД настраиваются в инфраструктурном typecast-слое: для простых VO общий `ValueObjectCast` может использовать публичные методы VO по соглашению, для сложных VO создаётся отдельный typecast-класс в `Infrastructure`. Entity хранит VO-свойства нативно (`public private(set) Email $email`) с `#[Column(typecast: Email::class)]` или с отдельным typecast-классом, без промежуточных raw-полей. CQRS Command принимает примитивы на внешней границе, Handler создаёт VO до вызова `Entity::create()` или доменных методов Entity.
- **Не заменять типизацию ручными проверками**: если ожидаемый тип известен, указывай его в сигнатуре метода. Не принимай `object`, `mixed` или слишком широкий тип с последующей проверкой `instanceof`. Исключение — границы системы и места, где входные данные действительно имеют неизвестный тип.
- **Валидация значений — только в ValueObject**: Entity не содержит методов валидации (`validateX()`, `mb_strlen()`, `trim()` и т.д.) для доменных значений. Вся валидация (длина, формат, пустота, диапазон, нормализация) инкапсулирована в соответствующем VO. Entity принимает готовый VO в `create()` и доменных методах, без голых примитивов и локальной валидации скаляров.
- **Исключения из ValueObject — это внутренние доменные ошибки (500)**: VO и доменные коллекции бросают `InvalidDomainValueException` (код 500), не `ValidationException` и не `\InvalidArgumentException`. `ValidationException` используется только на HTTP/API-границе, когда нужно вернуть пользователю ожидаемую ошибку 422. `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` возвращает для `InvalidDomainValueException` обычную 500-ошибку без исходного сообщения исключения в ответе.
- **Типизированные доменные исключения вместо `RuntimeException`**: запрещено использовать голый `\RuntimeException` с HTTP-кодом. Для ожидаемых HTTP-сценариев — свой класс: `NotFoundException` (404), `AuthenticationException` (401), `ForbiddenException` (403), `ValidationException` (422). Такие HTTP-исключения не логируются, а только превращаются в ответ пользователю. Для невалидного доменного значения — `InvalidDomainValueException` (500). Код зашит в конструкторе, передаётся только сообщение.
- **Исключения слоя — в папке `Exception/` этого слоя**: классы-исключения не лежат рядом с логикой, которая их бросает, а собираются в папке `Exception/` своего слоя — `App\Modules\{Module}\{Layer}\Exception` (`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`) и `App\Shared\{Layer}\Exception`. Слой исключения определяется контрактом, к которому оно относится, а не местом выброса: `OutboxMessageLoadingException` — часть контракта загрузчика сообщений, поэтому лежит в `Application/Exception`, хотя бросается в `Infrastructure`. Это распространяет на все слои и модули паттерн `Shared/Domain/Exception`.
- **Запрет `assert()`**: `\assert()` запрещён во всём коде проекта. Вместо assert — явная проверка условия с выбросом типизированного исключения. Причина: assert может быть отключён в production через `zend.assertions=-1`, что молча пропускает невалидные данные. В ValueObject публичные фабрики валидируют пользовательский ввод, а восстановление значений из БД выполняет инфраструктурный typecast-слой.

## Архитектура

- **Запрет try-catch в контроллерах, Handler-ах и бизнес-логике**: контроллеры и Handler-ы не ловят исключения — ошибки всплывают до `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`, который конвертирует их в `ErrorResponse` с корректным HTTP-кодом. `try-catch` допустим только в инфраструктурном коде (interceptor-ы, middleware, Job-обработчики) — там, где исключение ловится на границе системы. В Handler-ах вместо try-catch на `ConstrainException` использовать проверку перед записью (check-then-act) — дублирование при конкурентных запросах допустимо как 500, которую `ApiExceptionInterceptor` обработает. Ожидаемые клиентские ошибки бросать типизированными доменными исключениями (`AuthenticationException`, `ForbiddenException` и т.д.) с 4xx-кодом в `$code`; внутренние нарушения доменных значений — через `InvalidDomainValueException`. Даже в инфраструктурном коде запрещён try-catch, который ловит широкий `\Throwable` и лишь перебрасывает его как другой тип исключения, не добавляя реальной обработки (логирование, fallback, retry, изменение состояния, освобождение ресурса) — пусть исходное исключение всплывает к обработчику на границе. Оборачивать чужое исключение в доменный тип оправдано только тогда, когда это добавляет существенный контекст, нужный потребителю, или требуется контрактом границы; перетипизация без пользы — лишний код. Если же ошибку код обнаруживает сам (guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`.
- **Консольные команды — тонкие обёртки**: консольная команда только собирает ввод (аргументы, опции) и делегирует в соответствующий CQRS Command+Handler. Вся бизнес-логика — в Handler, консольная команда не содержит логики кроме маппинга ввода в Command DTO и вывода результата. Формат атрибута `#[AsCommand]` см. в примере консольной команды в `docs/code-examples.md`.
- **`env()` только в конфигах**: вызов `env()` допустим исключительно в файлах `app/config/*.php`. Сервисы, Handler-ы и любой другой код получают значения через типизированные конфиг-объекты (Spiral Config) или через DI. Прямое обращение к `env()` в бизнес-коде — нарушение.
- **Typed config для каждого config-файла**: при добавлении нового `app/config/*.php` сразу создаётся корневой DTO в `App\Shared\Infrastructure\Configuration\<Section>\<Section>Config`, который реализует `TypedConfig`. `configName()` должен совпадать с именем файла без `.php`. Вложенные секции описываются отдельными DTO рядом с корневым классом, а маппинг покрывается тестом через `ConfigMapper`.
- EnvironmentInterface - запрещён нужно использовать конфиги
- **Реквесты через Filter**: входящие данные принимаются через Spiral Filter-классы (`Spiral\Filter\Dto\FilterInterface`). Ассоциативные массивы `array $input` в контроллерах — запрещены. Enum-ы (`TenantStatus`, `TenantPlan` и т.д.) типизируются прямо в Filter-е — Spiral автоматически кастит строку в BackedEnum. Контроллер не делает `::from()`/`::tryFrom()` вручную.
- **Запрет `ServerRequestInterface` в контроллерах**: контроллеры не инжектят `Psr\Http\Message\ServerRequestInterface`. Для тела запроса — Spiral Filter (`#[Post]`, `#[Query]`). Для аутентифицированного пользователя — `#[Attribute(key: 'authUserId')]`, читающий request attribute из JWT middleware. Ключ `authUserId` (не `userId`) во избежание коллизий с параметрами роута. `ServerRequestInterface` допустим только в middleware и interceptor-ах.
- **Filter-ы полностью объектно-ориентированные**: свойства Filter-а — только скалярные типы, Enum-ы и вложенные Filter-ы/DTO. Ассоциативные массивы (`array<string, mixed>`) в Filter-е — запрещены. Для вложенных JSON-объектов создавать отдельный вложенный Filter или DTO (`#[NestedFilter]` / отдельный `readonly class`). Spiral поддерживает вложенность фильтров — использовать её вместо сырых массивов. Плоские типизированные списки (`list<string>`, `list<int>`, `list<UuidString>`) — допустимы, в том числе nullable (`?array` с PHPDoc `@var list<string>|null`).
- **Filter: не указывать `key` если совпадает с именем свойства**: `#[Post]` без `key` — Spiral автоматически берёт имя PHP-свойства. `key` указывать только когда имя в JSON отличается от имени свойства. Поскольку API использует camelCase и PHP-свойства тоже camelCase — `key` практически никогда не нужен.
- **Filter: обязательные свойства без значения по умолчанию**: если свойство Filter-а обязательное — оно объявляется без значения по умолчанию и без nullable. `public string $email;` — не `public string $email = '';`. Spiral заполняет свойства из запроса, валидация через `#[Assert\NotBlank]` гарантирует наличие. Дефолт `= ''` маскирует отсутствие значения. Nullable (`?string $name = null`) и дефолты (`int $limit = 20`) — только для реально опциональных полей. Для файлов: `public UploadedFileInterface $file;` — не nullable.
- **Респонс — типизированный класс**: каждый ответ API — объект с типизированными полями, не ассоциативный массив. Запрещено возвращать `['key' => $value]` напрямую.
- **Запрет ассоциативного массива как структуры (record)**: ассоциативный массив с фиксированным, заранее известным набором именованных ключей, где значения по ключам имеют разный смысл (`['userId' => .., 'type' => .., 'sessionId' => ..]`), — это скрытый DTO и запрещён везде в коде проекта. Заменять на DTO/VO/Response-класс или enum. Признак нарушения (проверяется на ревью): ключи массива записаны строковыми литералами в коде, либо к массиву обращаются по константному ключу (`$x['userId']`). PHPDoc-тип `array<string, string>` (как и любой `array<string, …>`) такой массив НЕ легализует — он лишь маскирует структуру под «карту», поэтому «значения одного типа» сами по себе массив не оправдывают. Разрешён ассоциативный массив только как однородная карта (map): ключ — это данные времени выполнения (идентификатор, ключ кеша, код локали), набор ключей заранее не известен и не ограничен, а все значения имеют один тип и один смысл; типизируется `array<string, T>`, где `T` — не массив, shape или tuple (см. «Именованные типы для сложных данных»). Когда карта становится самостоятельным доменным понятием, которое передаётся между слоями или несёт поведение/инварианты, — оборачивать в именованную типизированную коллекцию; для разовой инфраструктурной карты raw `array<string, T>` допустим. Исключения, где ассоциативный массив неизбежен и нарушением не считается: контекст логгера (`['userId' => $id]`), `jsonSerialize()` и другая сериализация в JSON, конфиги фреймворка (`app/config/*.php` и их config-DTO), а также payload/headers/параметры на самой границе с чужим контрактом — vendor-интерфейсом, Cycle ORM, Spiral Queue, translator, PSR-интерфейсами. На такой границе массив навязан библиотекой; внутрь нашего кода он должен сразу превращаться в VO/DTO/enum.
- **Базовые Response-классы**: для API-ответов использовать `DataResponse<T>` (одиночный объект), `PaginationResponse<T>` (постраничный список), `CollectionResponse<T>` (коллекция без пагинации). Response-классы параметризованы дженерик-типом `T` возвращаемого ресурса.
- **Ресурсы наследуют `AbstractResource`**: все API-ресурсы — `final readonly class` extends `App\Shared\Presentation\Http\Resource\AbstractResource`. Определяют только свойства и `fromEntity()`. Сериализация автоматическая через рефлексию, `jsonSerialize()` вручную не определяется.
- **camelCase везде кроме БД**: все ключи в любых сериализуемых данных — camelCase. Это касается: HTTP request/response JSON, payload очередей (queue jobs), WebSocket-сообщений (Centrifugo), event payload, логгер-контекста, PSR-7 request attributes. Единственное исключение — имена таблиц и колонок в БД (PostgreSQL convention: snake_case), Cycle ORM аннотации для колонок, переменные окружения и ключи конфигов фреймворка. Если ключ попадает в JSON, очередь, лог или передаётся между слоями — он camelCase.
- **Контроллеры возвращают Response напрямую**: методы контроллеров возвращают базовые Response-классы (`DataResponse`, `CollectionResponse`, `PaginationResponse`, `ErrorResponse`) напрямую, без промежуточных обёрток. Return type метода — конкретный Response-класс.
- **PHPDoc `@return` с дженериком на каждом методе контроллера**: если метод возвращает базовый Response-класс, обязателен `@return DataResponse<SuperUserResource>` (или аналогичный) в PHPDoc. Это даёт PHPStan полную информацию о типе ресурса внутри ответа.
- **OpenAPI metadata не обязательна**: `#[OpenApi(id: ..., description: ...)]` используется только для ручного уточнения `operationId` и описания. Если атрибута нет, генератор строит операцию из route name, controller method, PHPDoc, Filter DTO, Response/Resource и enum-ов. Перед релизом запускать `php app.php openapi:generate` и проверять обновление `public/openapi/openapi.yml`.
- **Контроллеры — тонкие обёртки**: контроллер не содержит бизнес-логики и логики выборки. Контроллер только: принимает Filter, вызывает Command Handler (для записи) или Query Handler (для чтения), маппит результат в Resource и оборачивает в Response-класс. Никаких `WHERE`-условий, `if-else`, подсчётов, ручной пагинации — вся логика выборки в Query Handler.
- **CQRS: Command + Query**: запись через Command, чтение через Query; каждая операция — DTO + Handler. Выборку пишем явно в репозиториях, без Data Grid.
- **Репозитории — отдельный слой модуля**: Repository лежит в `Modules/{Module}/Repository`. `Application` своего модуля может использовать свои Repository напрямую через constructor injection. Репозиторий другого модуля использовать нельзя: для связи между модулями обращаться только к `Application` другого модуля.
- **В папке `Repository` — только репозитории, работающие с сущностями**: `Modules/{Module}/Repository` содержит исключительно `*Repository`-классы. Репозиторий принимает критерии запроса и возвращает доменные сущности (`Entity`) и их коллекции; скаляр или enum допустим только как результат запроса состояния (`count`, `exists`, статус). DTO, read-model, типизированные представления сырой строки БД, value object-ы и прочие вспомогательные классы в папке `Repository` запрещены — им место в `Domain`/`Application`/`Infrastructure`. Если строки нужно прочитать в обход ORM-гидрации (например, обработка повреждённых данных), такой код и его типы живут в `Infrastructure`, а не в репозитории.
- **Репозитории без обязательных контрактов**: `RepositoryContract` не создаётся автоматически. Контракт для репозитория добавляется только при реальной причине: несколько реализаций, сложная подмена в тестах или необходимость полностью отвязать сценарий от конкретной ORM.
- **Контракты технических сервисов**: если `Application` нужен технический сервис, контракт лежит в `Modules/{Module}/Application/Contract` и заканчивается на `Contract`. Реализация лежит в `Infrastructure` своего модуля. Пример: `MediaFileServiceContract` и `S3MediaFileService`.
- **Доменные методы в репозиториях**: запрещены generic-вызовы `findByPK()`, `findOne()`, `select()` в Handler-ах и другом бизнес-коде. Репозиторий предоставляет конкретные доменные методы: `findByEmail()`, `findByTokenHash()`, `findLatestByUserId()`. Базовые методы Repository (`findOne`, `findByPK`) используются только внутри самого Repository для реализации доменных методов.
- **Без лишнего `instanceof` в репозиториях**: если репозиторий наследуется от `Cycle\ORM\Select\Repository` с PHPDoc `@extends Repository<Entity>`, методы Cycle (`findByPK()`, `findOne()`, `findAll()`, `select()->fetchOne()`) уже типизируются как эта Entity. Не писать `return $entity instanceof Entity ? $entity : null;` после таких вызовов — возвращать результат напрямую.
- **Параметры методов Repository только про запрос**: методы Repository принимают только критерии выборки, сортировки, пагинации или блокировки, относящиеся к конкретному запросу. Нельзя передавать в методы Repository технические зависимости вроде `DatabaseInterface`, `Select`, `EntityManagerInterface`, config, logger или service-объекты. Такие зависимости передаются через constructor injection и остаются внутренней деталью Repository.
- **Выборки в Repository через ORM Select**: внутри Repository для чтения сущностей использовать `$this->select()` с доменными методами Repository. Не строить выборку сущностей через `$database->select()->from('table_name')`, если то же можно выразить через ORM Select. Query Builder допустим только для операций, которые ORM Select не умеет выразить, и причина должна быть понятна из кода.
- **Репозиторий — только чтение (read-only)**: Repository только читает данные — через ORM `$this->select()` и базовые `findByPK()`/`findOne()`/`findAll()`. Репозиторий не сохраняет и не изменяет данные: запрещены методы-мутаторы `save()`, `persist()`, `store()`, `create()`, `update()`, `delete()`, а также инъекция `EntityManagerInterface` в репозиторий. Прямые `$database->update()`, `$database->insert()`, `$database->delete()` тоже запрещены — даже атомарный CAS-переход по статусу. Любое изменение состояния выражается доменным методом Entity (`markQueued()`, `markFailed()` и т.д.) и сохраняется вызовом `$this->entityManager->persist($entity)` + `$this->entityManager->run()` в Handler-е или инфраструктурном orchestrator-е, который вызывает репозиторий только для выборки. Правило rules.md «Запрет SQL текстом» отвечает на вопрос *как* писать запрос (query builder, не сырая строка), но не разрешает репозиторию модифицировать данные. Санкционированное исключение для массовой записи без доменных инвариантов — отдельный класс с маркером `App\Shared\Infrastructure\Database\SetBasedWrite` (см. правило «Set-based запись — только через маркер `SetBasedWrite`»); сам репозиторий остаётся read-only в любом случае.
- **Set-based запись — только через маркер `SetBasedWrite` и только при доказанной необходимости**: прямая массовая запись (`update()`/`insert()`/`delete()` на `Cycle\Database\DatabaseInterface`) по умолчанию запрещена и механически блокируется правилом `DisallowSetBasedWriteRule` — обычное изменение состояния идёт через доменный метод Entity + `EntityManager`. Исключение разрешается, только когда выполнены **оба** блока условий.
  - **Необходимость (хотя бы один пункт):** (1) число затрагиваемых строк не ограничено доменом сверху и растёт с данными или активностью пользователя (mark-all-read, удаление/архивация старых записей, сброс по типу) — загрузка всего набора как Entity даёт неограниченную память и время; (2) операция на горячем пути и вызывается так часто, что стоимость гидрации сущностей значима на масштабе; (3) это чистый «штамп» одной колонки/статуса по набору (флаг, таймстемп, счётчик), где загруженные сущности всё равно не используются. Числовой порог намеренно не задаётся: критерий — «набор не ограничен сверху», а не «больше N».
  - **Безопасность (все пункты обязательны):** у строки нет доменного инварианта, валидации или ветвления, которые должны отработать при записи; переход идемпотентный и тривиальный; затронутые строки не читаются и не используются как живые Entity в этом же запросе.
  - **Запрещено, даже если соблазнительно:** одиночная сущность; набор, заведомо ограниченный доменом небольшим числом; наличие per-row инварианта/проверки/вычисляемых полей (тогда — доменный путь, при необходимости порциями); чтение затронутых строк обратно в том же запросе; мотив «так меньше кода / быстрее писать».
  - Каждый метод set-based writer-а — именованная доменная операция (`markAllReadForRecipient`), а не generic `update(table, set, where)`; writer-классы держатся маленькими и узкими, каждый метод покрыт тестом. Маркер реализуется на классе-реализации в `Infrastructure`, а Application обращается к нему через `*Contract`. Блок «безопасность» PHPStan не проверяет — это зона ревью и докблока.
- **Не обходить ORM ради «свежего» чтения**: все изменения проходят через Entity и общий identity map ORM, поэтому Entity в памяти и есть актуальное состояние. Запрещено добавлять сырые `$database->select()` «чтобы перечитать свежий статус мимо identity map» — читать саму Entity или доменный метод репозитория на ORM Select. Если в sync-сценарии другой обработчик меняет состояние в том же процессе, он меняет ту же Entity, и она уже актуальна. Потребность в read мимо гидрации почти всегда сигнал, что изменение должно было пройти через Entity.
- **Запрет транзакций внутри Repository**: Repository не открывает, не коммитит и не откатывает транзакции (`transaction()`, `begin()`, `commit()`, `rollback()`). Граница транзакции находится в Handler-е, Application-сценарии или инфраструктурном orchestrator-е, который вызывает Repository. Repository отвечает только за конкретные запросы (чтение).
- **Доступ к БД только через репозитории**: прямые запросы к базе данных (`$database->query()`, `$database->table()`, `$orm->getRepository()`, инлайн `Select`, raw SQL) запрещены в Handler-ах, контроллерах, сервисах и любом бизнес-коде. Весь доступ к данным — исключительно через методы Repository-классов своего модуля. Это обеспечивает единую точку доступа к данным и предотвращает дублирование запросов. Исключение — миграции и инфраструктурный код (console-команды для обслуживания БД).
- **Запрет SQL текстом**: ручные SQL-строки в `$database->query()` и `$database->execute()` запрещены в коде приложения и тестах. Для выборки, вставки, обновления и удаления всегда использовать Cycle ORM Select или Cycle Database Query Builder (`select()`, `insert()`, `update()`, `delete()`). Исключение — миграции, если это невозможно выразить через schema/query builder и причина явно обоснована рядом с кодом.
- **Формат даты для БД через общий контракт**: если дату нужно явно форматировать для query builder или другого запроса к БД, формат нельзя писать строкой прямо в вызове `format()` и нельзя заводить локальную константу в отдельном репозитории. Используй общий `App\Shared\Infrastructure\Database\DatabaseDateTimeFormat`, чтобы формат был единым контрактом хранения.
- **Typecast VO: конвенция или отдельный класс**: для non-nullable VO, отображаемого на скаляр (id, тип, счётчик, enum), достаточно общего `App\Shared\Infrastructure\Cycle\ValueObjectCast` по соглашению — cast из БД через `fromString(string)`/`fromInt(int)`/`BackedEnum::from()`, запись через `value()` (скаляр или `DateTimeInterface`). Отдельный `ColumnValueTypecast`-класс в `Modules/{Module}/Infrastructure/Cycle` (статические `castDatabaseValue()`/`uncastValue()`) нужен только для случаев, которые конвенция не выражает: nullable-колонка при non-null VO-свойстве (null-object `none()`/`permanent()` — иначе `NULL` превратится в `null` и сломает типизированное свойство), дата/время (нужна фабрика `fromDateTime`), JSON или коллекция (`fromJson`/`json_encode` вместо `fromString`/`value()`).
- **Без pass-through typecast-обёрток**: не создавать per-module typecast-класс, который реализует `Castable`/`UncastableInterface` и только делегирует в `ValueObjectCast` без своей логики. Entity ссылается на общий движок напрямую: `typecast: [Typecast::class, ValueObjectCast::class]`. Отдельный класс заводится только при наличии собственной логики преобразования (см. предыдущее правило).
- **Stateful typecast — не синглтон**: `ValueObjectCast` хранит правила (`$rules`) для конкретной роли Entity, поэтому stateful; Cycle создаёт по одному обработчику на роль через `factory->make()`. Его (и любой stateful typecast) нельзя помечать `#[Singleton]` или биндить общим синглтоном в контейнере — иначе правила разных сущностей смешаются.
- **Логирование: DEBUG по умолчанию**: бизнес-логика логирует на уровне DEBUG. INFO — только для ключевых бизнес-событий (успешная регистрация, успешный сброс пароля). WARN — реальные проблемы инфраструктуры. ERROR — сбои, нарушения инвариантов. Неверный пароль, истёкший код, невалидный токен — это нормальный flow пользователя → DEBUG, не WARNING.
- **Centrifugo через свой HTTP-клиент**: для взаимодействия с Centrifugo используется клиент в `Modules/{Module}/Infrastructure/Centrifugo` или в `Shared/Infrastructure`, если он нужен нескольким модулям (прямые curl-запросы к HTTP API). Пакет `centrifugal/phpcent` удалён (deprecated `curl_close()` на PHP 8.5). Сервис `CentrifugoService` — обёртка над `CentrifugoClient`.
- **Внешние события и отложенные шаги через outbox**: события, которые вызывают внешние побочные эффекты (Centrifugo, email, push, webhooks), сохраняются как DTO в transactional outbox в той же транзакции, что и бизнес-изменение. Handler-ы и Spiral event listener-ы не вызывают такие интеграции напрямую. Публикация выполняется отдельным relay-процессом после commit-а с повторами и статусами доставки. Тот же механизм применяется и к внутренним отложенным шагам, которые должны гарантированно выполниться после commit-а бизнес-транзакции, даже если их побочный эффект остаётся внутри системы (например, асинхронная обработка загруженного медиа: `MediaUploaded` кладётся в outbox в одной транзакции с переходом медиа в `uploaded`, а relay запускает обработку). Критерий — нужна надёжная доставка «после commit», а не природа эффекта (внешний или внутренний).
- **Outbox relay запускается в одном экземпляре**: текущий relay использует `FOR UPDATE` без `SKIP LOCKED`, потому что ручной SQL запрещён, а Cycle ORM Select не даёт отдельного API для `SKIP LOCKED`. До отдельного решения по безопасному `SKIP LOCKED` нельзя запускать несколько постоянных `outbox:relay --loop` одновременно.
- **Запрет yii-error-handler-bridge**: пакет `spiral-packages/yii-error-handler-bridge` удалён — HTML/XML рендеры ошибок не нужны в API-only проекте. Ошибки обрабатываются через `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`.
- **`entityManager->run()` вызывается в Handler-е**: каждый Handler сам вызывает `$this->entityManager->persist()` / `$this->entityManager->delete()` и затем `$this->entityManager->run()` для flush. Транзакцию вокруг dispatch включает только `#[Transactional]` на `Handler::handle()`. Если атрибута нет, dispatch выполняется без транзакции. `#[NonTransactional]` не используется. `run()` остаётся внутри Handler-а, чтобы сценарий явно управлял моментом flush. Исключение — технический `outbox:relay`: это инфраструктурный orchestrator после commit-а бизнес-транзакции, поэтому он сам фиксирует claim, publish-failure и queue-status переходы.
- **Queue push по FQCN класса**: задачи пушатся в очередь по имени класса Job (`SendEmailVerificationCodeJob::class`). Промежуточные enum-ы для имён задач — лишняя прослойка. Spiral Queue маршрутизирует по имени класса напрямую.
- **Filter-ы живут в модуле**: Filter-классы — часть HTTP-слоя (парсинг HTTP-запросов), не доменной логики. Размещать в `Modules/{Module}/Presentation/Http/Filter/{Area}`. Например: `App\Modules\Auth\Presentation\Http\Filter\LoginFilter`, `App\Modules\User\Presentation\Http\Filter\UpdateUserProfileFilter`. Папка `App\Filter` запрещена.
- **View-шаблоны живут в модуле**: шаблоны представления модуля (twig: письма, Swagger UI, любые рендеримые view) — часть Presentation-слоя и лежат в `Modules/{Module}/Presentation/views/`. Каждый модуль регистрирует свою папку шаблонов как namespace = имя модуля в нижнем регистре через `ViewsBootloader::addDirectory(...)` в bootloader-е модуля (путь к папке строится от `__DIR__`), а ссылка на шаблон использует namespace-форму `{module}:<имя>` — например `auth:login-code`, `system:swagger/index`. Общая папка `app/views` (default namespace) для шаблонов модулей не используется. Это контраст с двумя сущностями, которые осознанно остаются глобальными: config-файлы (`app/config/*.php` + typed DTO в `Shared/Infrastructure/Configuration`, см. «Typed config для каждого config-файла» и `arch.md`) и миграции (`app/database/migrations` — единая линейная история схемы, часто кросс-модульная). Шаблон — живой ассет модуля, рендерится в рантайме и переезжает вместе с модулем; конфиг и миграция — нет.
- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/` и `Modules/{Module}/Application/Query/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пока в модуле одна область (Area), подпапка действия лежит прямо в корне `Command/`/`Query/`; уровень области `{Area}` добавляется только когда областей становится несколько. Пример: `Modules/Auth/Application/Command/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Login`.
- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/UpdateUserProfile/`, `Application/Query/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.


## Типовые контракты

- **Без неявного `mixed`**: generic-параметры указываются явно. Нельзя писать `array`, если контракт на самом деле ожидает `list<Foo>` или `array<int, Foo>`.
- **Без сложных массивов в PHPDoc**: вложенные массивы, tuple-типы и array shapes запрещены, например `array<string, array<int, string>>` и `array{foo: string}`.
- **Без явного `mixed`**: `mixed` не используется в проектных контрактах, включая параметры, return type, PHPDoc generic-и и callbacks. Неизвестное значение нужно сузить на границе системы и дальше передавать именованный DTO/VO/enum, конкретный union type или типизированную коллекцию.
- **Именованные типы для сложных данных**: вместо сложных generic-контейнеров используйте DTO/value object, enum или коллекции. Допустимы простые `list<T>` и `array<int|string, T>`, где `T` не является массивом, shape или tuple.

## Тестирование
- 100 процентное покрытие тестами
- Каждый роут должен быть покрыт интеграционным тестом
- **Стаб вместо `expects()` для дублёров без проверки вызова**: если дублёр нужен только чтобы вернуть данные (не для проверки факта/числа вызовов), используй `createStub()` + `willReturn*()`, а не `createMock()` с `->method()` без `expects()` и не `->expects($this->any())`. В PHPUnit 13 `with()`/`method()` без `expects()` деприкейтнут, а `expects($this->any())` тоже деприкейтнут (удаляется в PHPUnit 14). Для подбора возврата по аргументу — `willReturnMap([[ $arg, $value ]])`. Важный нюанс: при несовпадении аргумента `willReturnMap` возвращает дефолт по типу возврата метода (для `: array` это `[]`, не исключение), поэтому стаб сам по себе не проверяет аргумент. Для дублёров конфигуратора (`getConfig(): array`) это допустимо, пока у целевого DTO есть обязательные поля — маппинг пустого `[]` падает и косвенно ловит «не ту секцию». Если добавляешь конфиг, у которого все поля опциональны/с дефолтами, заведи отдельный позитивный тест на «ожидаемая секция запрошена» (например `willReturnCallback`, бросающий при несовпадении), иначе неверная секция пройдёт молча.

## Проверки

- **Тесты приложения**: локальная проверка запускается командой `make test`.
- **PHPStan приложения**: локальная проверка запускается командой `make phpstan`.
- **PHPStan tooling**: живёт отдельным Composer-пакетом в `packages/phpstan-strict-rules`. Его тесты и fixtures должны оставаться внутри этого пакета, а не в корневом `tests/`.
- **Packages-пакеты изолированы**: каждый пакет в `packages/*` является отдельным Composer-пакетом со своим `composer.json`, локальным `vendor/`, package-local `bootstrap.php`, `phpunit.xml` и `phpstan.neon`. Bootstrap и Composer scripts внутри `packages/*` не должны ссылаться на корневой `../../vendor` или `../../vendor/bin`. Для проверки пакета используется `composer -d packages/<package> install`, затем `composer -d packages/<package> test` и `composer -d packages/<package> phpstan`.

 succeeded in 0ms:
# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
```

## Обработчик команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(LoginCommand $command): LoginResult
    {
        $user = $this->userRepository->findByEmail(Email::from($command->email))
            ?? throw new AuthenticationException('Неверный email или пароль');

        if (!$user->passwordHash->verify($command->password)) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        return new LoginResult(
            accessToken: $user->issueAccessToken(),
            refreshToken: $user->issueRefreshToken(),
        );
    }
}
```

## DTO запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
```

## Typed config

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class PaymentConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'payment';
    }

    /**
     * @param array<string, PaymentProviderConfig> $providers
     */
    public function __construct(
        public string $default,
        public array $providers,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

final readonly class PaymentProviderConfig
{
    public function __construct(
        public string $dsn,
        public bool $sandbox,
    ) {}
}
```

Тест на `Tests\TestCase` поднимает Spiral kernel, поэтому живёт в suite `Kernel`
(`tests/Kernel`), а не в лёгком `Unit`:

```php
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Payment\PaymentConfig;
use Tests\TestCase;

final class PaymentConfigTest extends TestCase
{
    public function testPaymentConfigMapsFromConfigurator(): void
    {
        $config = $this->getContainer()
            ->get(ConfigMapper::class)
            ->map(
                section: PaymentConfig::configName(),
                targetClass: PaymentConfig::class,
            );

        self::assertSame('stripe', $config->default);
        self::assertArrayHasKey('stripe', $config->providers);
    }
}
```

## Обработчик запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\User\Repository\UserRepository;

final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserProfileQuery $query): User
    {
        return $this->userRepository->findById($query->userId)
            ?? throw new NotFoundException('Пользователь не найден');
    }
}
```

## Контроллер

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Controller;

use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileQuery;
use App\Modules\User\Presentation\Http\Resource\UserResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Spiral\Router\Annotation\Route;

final readonly class UserController
{
    /**
     * @return DataResponse<UserResource>
     */
    #[Route(route: '/api/v1/users/<id>', name: 'api.v1.user.show', methods: ['GET'])]
    public function show(
        string $id,
        GetUserProfileHandler $getUserProfileHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $query = new GetUserProfileQuery(
            userId: $id,
        );

        $user = $queryBus->dispatch(
            query: $query,
            handler: $getUserProfileHandler->handle(...),
        );

        return new DataResponse(UserResource::fromEntity($user));
    }
}
```

## Сущность

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\User\Domain\ValueObject\DisplayName;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\PasswordHash;
use App\Modules\User\Domain\ValueObject\Username;
use App\Modules\User\Infrastructure\Cycle\UserValueObjectTypecast;
use App\Modules\User\Repository\UserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, UserValueObjectTypecast::class],
)]
class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string', typecast: PasswordHash::class)]
    public private(set) PasswordHash $passwordHash;

    #[Column(type: 'string', typecast: Username::class)]
    public private(set) Username $username;

    #[Column(type: 'string', typecast: DisplayName::class)]

exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD

## Обзор

YogaLoka - API-first backend для мобильного приложения на PHP 8.5, Spiral
Framework, RoadRunner и Cycle ORM.

Приложение работает как модульный монолит: один runtime, один deploy и один код
приложения. Код группируется в модули по предметной области.

## Модули

Проект устроен как модульный монолит с прагматичным DDD-подходом.

Бизнес-логика живёт в `Domain`. Важные значения оформляются как
`ValueObject`, сущности содержат поведение, а технические детали вынесены в
`Infrastructure`.

DDD-паттерны не вводятся автоматически. Aggregate, Domain Service, Domain Event,
отдельная read model или anti-corruption layer добавляются только когда они
решают реальную проблему в коде.

Модуль - это отдельная область приложения: `Media`, `User`, `Feed`, `Auth`.

Структура нового модуля:

```text
Modules/
  Media/
    Domain/
    Application/
      Contract/
    Repository/
    Infrastructure/
      Cycle/
      FileService/
    Presentation/
```

Назначение слоёв:

```text
Domain         - бизнес-логика модуля.
Application    - сценарии модуля и контракты для технических зависимостей.
Repository     - доступ к БД своего модуля через доменные методы.
Infrastructure - технические реализации контрактов, Cycle typecast, S3, внешние сервисы.
Presentation   - HTTP-контроллеры, фильтры запросов и другие входы.
```

Исключения каждого слоя лежат в папке `Exception/` своего слоя:
`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`, а
общие доменные — в `Shared/Domain/Exception`. Слой исключения определяется
контрактом, к которому оно относится, а не местом выброса: например
`OutboxMessageLoadingException` лежит в `Application/Exception`, хотя бросается в
инфраструктурной реализации загрузчика сообщений.

Правила связей:

```text
Domain не зависит от других слоёв.

Application может использовать:
- свой Domain;
- свой Repository;
- свои Contract;
- Application других модулей.

Infrastructure реализует Contract своего модуля.

Presentation вызывает Application своего модуля.
```

Контракты лежат в:

```text
Modules/{Module}/Application/Contract
```

Имена контрактов заканчиваются на `Contract`.

Примеры:

```text
MediaFileServiceContract
```

Репозитории лежат в:

```text
Modules/{Module}/Repository
```

Пример:

```text
MediaRepository
```

Репозитории не являются публичным API модуля. `Application` своего модуля может
использовать свои репозитории напрямую, но другие модули не обращаются к ним.

Технические реализации контрактов лежат в `Infrastructure`:

```text
Infrastructure/
  Cycle/        # typecast и другие классы для Cycle ORM
  FileService/  # техническая работа с файлами
```

Примеры:

```text
S3MediaFileService
```

Общий доменный код, который не принадлежит одному модулю, лежит в `Shared`.

Пример:

```text
Shared/
  Domain/
    Exception/
    Trait/
    ValueObject/
  Presentation/
    Http/
      Resource/
```

Другие модули могут обращаться только к `Application`.

Хорошо:

```text
User/Application -> Media/Application
```

Плохо:

```text
User/Application -> Media/Infrastructure
User/Infrastructure -> Media/Infrastructure
User -> media_files table
User -> MediaRepository
```

Пример:

```text
User/Application/SetAvatar
  -> Media/Application/CheckMediaIsImage
  -> User/Domain/User::setAvatar
```

`Media` не должен знать про аватар. Аватар - это часть `User`.

`Media` должен давать только свои сценарии:

```text
CreateMedia
DeleteMedia
CheckMediaExists
CheckMediaIsImage
GetMediaUrl
```

## Локальный Docker-runtime

Локальная разработка и проверки выполняются через Docker Compose из
`docker/docker-compose.dev.yml`. Приложение запускается в двух RoadRunner runtime:

- `app-http`: RoadRunner слушает `0.0.0.0:8080` только внутри контейнера.
  Наружу compose публикует сервис как `127.0.0.1:60080 -> 8080`. RoadRunner
  jobs consumer для RabbitMQ работает в том же процессе.
- `temporal-worker`: отдельный Temporal worker на task queue `default`.

Dev runtime использует RabbitMQ как queue connection по умолчанию. Memory
pipeline остаётся запасным вариантом для локальных экспериментов и обратной
совместимости, но не является основной очередью dev-стенда. Redis в локальном
стенде используется для cache/session и RoadRunner KV, но не является брокером
очереди.

Локальная инфраструктура: PostgreSQL, Redis, RabbitMQ, MinIO, Mailpit, Temporal,
Temporal UI и Centrifugo. Dev storage по умолчанию использует MinIO bucket
`yoga-loka`, тесты используют отдельные `yoga_loka_test` и `yoga-loka-test`.

## Структура каталогов

```
app/
  config/                         # Spiral config-файлы; env() допустим только здесь
  database/
    migrations/                   # Cycle ORM миграции
  locale/                         # Переводы
  src/
    Modules/
      Media/
        Domain/                   # Entity, ValueObject, Enum, доменные коллекции
        Application/              # Command/Query сценарии модуля
          Contract/               # Контракты технических сервисов, *Contract
        Repository/               # Cycle repositories с доменными методами
        Infrastructure/
          Cycle/                  # Typecast и другие классы Cycle ORM
          FileService/            # Реализации файловых сервисов
        Presentation/             # HTTP, console, queue, Temporal входы модуля

      Outbox/
        Domain/                   # Outbox-события, статусы, value object
        Application/
          Command/                # Сценарии relay и обработки outbox-сообщений
          Contract/               # OutboxEventStoreContract и сериализация сообщений
          Exception/              # Исключения слоя Application (загрузка, сериализация сообщений)
          Message/                # DTO сообщений outbox
        Repository/               # Доступ к outbox_events
        Infrastructure/
          Bootloader/             # OutboxBootloader, OutboxConsoleBootloader
          Exception/              # Исключения слоя Infrastructure (registry, relay)
          Relay/                  # Relay, worker, loop control, sleeper
          Queue/                  # Publisher, serializer, headers, status interceptor
          Message/                # Event store, message loader, message serializer
          Registry/               # Реестр пары message -> Job
          Cycle/                  # Typecast outbox-полей
        Presentation/
          Console/                # outbox:relay
          Job/                    # Технические Job outbox

      System/
        Infrastructure/
          Bootloader/             # SystemBootloader: регистрация namespace `system` для view
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow
          views/                  # twig-шаблоны модуля (swagger/index), namespace `system`

    Shared/
      Domain/
        Exception/                # Общие доменные исключения
        Trait/                    # Общие доменные трейты
        ValueObject/              # Общие базовые VO и общие идентификаторы
      Presentation/
        Http/
          Resource/               # Общие базовые API-ресурсы
      Infrastructure/
        Cache/
        Configuration/            # Типизированные config DTO и ConfigMapper
        Cycle/                    # Общие typecast-классы
        Exception/                # Общие инфраструктурные исключения (config mapping)
        Framework/                # Spiral Kernel, bootloaders, routes
```

## Правила зависимостей

Внешние слои могут зависеть от внутренних, внутренние не зависят от внешних.

```text
Presentation   -> Application своего модуля, Response/Resource, framework attributes
Application    -> Domain, Repository своего модуля, Contract своего модуля

exec
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-17_13-27_posts-comments-db.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
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

codex
План оказался подробным, но уже видно несколько мест, которые надо проверять по фактическим шаблонам: как называются роли сущностей, как устроены nullable typecast, и как тестовая база управляет миграциями. Дальше читаю оставшуюся часть плана и сами эталонные классы.
exec
/bin/zsh -lc "sed -n '261,620p' docs/plans/2026-06-17_14-16_posts-comments-db-layer.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
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
  `CommentText`/`TagText` → исключение, пустой `PostText` → `none()`.
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
  CommentDeletedAt, CommentDeletionReason)`/`restore()`; `incrementLikes()`/
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
- **`PostBlock`** — зеркало `UserBan`: `PostBlockId`, `PostId`, `BlockReason`,
  `UserId $blockedBy`, тройка разблокировки (`BlockUnblockedAt`/`BlockUnblockedBy`/
  `BlockUnblockedReason` со своими typecast), `HasTimestamps`; методы
  `create(PostId, BlockReason, UserId $blockedBy)`, `markUnblocked(BlockUnblockedBy,
  BlockUnblockedAt, BlockUnblockedReason)`, predicate `isActive()`.

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

Сценарии тестирования: схема накатывается на чистой тестовой БД без ошибок FK;
все таблицы, unique- и обычные индексы присутствуют; `down()` дропает без
висящих FK. Практически проверяется тем, что фаза 5 (feature-тесты на реальной
схеме) проходит; отдельный тест миграции не пишем, если существующие модули его
не имеют.

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
  ?string $cursor = null, int $limit = 20): PostCollection` (cursor — `id` UUID v7
  или `null` для первой страницы, `WHERE id < :cursor`, `ORDER BY id DESC`, опц.
  фильтр статуса через `when()`), `findRepostsOf(PostId): PostCollection`.
- `CommentRepository`: `findById(CommentId): ?Comment`, `findByPostId(PostId
  $postId, ?string $cursor = null, int $limit = 20): CommentCollection`,
  `findReplies(CommentId $parentId, ?string $cursor = null, int $limit = 20):
  CommentCollection` (родителя принимаем как `CommentId`, не null-object — критерий
  запроса всегда конкретен).
- `TagRepository`: `findById(TagId)`, `findByText(TagText): ?Tag`,
  `findByTexts(list): TagCollection`.
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
Tests\DatabaseTestCase`, `persist()`/`run()`/`cleanOrmHeap()`):
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

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use BackedEnum;
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;

final class ValueObjectCast implements CastableInterface, UncastableInterface
{
    /**
     * Правила привязаны к конкретной роли Entity, поэтому класс stateful. Cycle
     * создаёт по одному typecast-обработчику на роль через factory->make().
     * Не биндить как #[Singleton] / общий синглтон — иначе правила разных ролей смешаются.
     *
     * @var array<non-empty-string, class-string>
     */
    private array $rules = [];

    /**
     * @param array<non-empty-string, mixed> $rules
     * @return array<non-empty-string, mixed>
     */
    #[\Override]
    public function setRules(array $rules): array
    {
        foreach ($rules as $field => $rule) {
            if (!\is_string($rule) || !\class_exists($rule)) {
                continue;
            }

            if (!$this->supportsRule($rule)) {
                continue;
            }

            /** @var class-string $rule */
            $this->rules[$field] = $rule;
            unset($rules[$field]);
        }

        return $rules;
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function cast(array $data): array
    {
        foreach ($this->rules as $field => $rule) {
            if (!\array_key_exists(key: $field, array: $data)) {
                continue;
            }

            $data[$field] = $this->castField(rule: $rule, value: $data[$field]);
        }

        return $data;
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, bool|int|float|string|\DateTimeInterface|null>
     */
    #[\Override]
    public function uncast(array $data): array
    {
        foreach ($data as $field => $value) {
            $data[$field] = $this->uncastField(
                field: (string) $field,
                value: $value,
            );
        }

        return $data;
    }

    /**
     * @param class-string $rule
     */
    private function supportsRule(string $rule): bool
    {
        return (
            \is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)
            && \method_exists(object_or_class: $rule, method: 'castDatabaseValue')
            && \method_exists(object_or_class: $rule, method: 'uncastValue')
        )
            || \is_subclass_of(object_or_class: $rule, class: BackedEnum::class)
            || \method_exists(object_or_class: $rule, method: 'fromString')
            || \method_exists(object_or_class: $rule, method: 'fromInt');
    }

    /**
     * @param class-string $rule
     */
    private function castField(
        string $rule,
        bool|int|float|string|object|null $value,
    ): object|null {
        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
            return $this->invokeColumnCast(
                rule: $rule,
                value: $this->databaseValueOrFail($value),
            );
        }

        if ($value === null) {
            return null;
        }

        if (\is_subclass_of(object_or_class: $rule, class: BackedEnum::class)) {
            if (!\is_string($value) && !\is_int($value)) {
                throw new \InvalidArgumentException('Enum-значение базы должно быть строкой или числом.');
            }

            return $rule::from($value);
        }

        if (\method_exists(object_or_class: $rule, method: 'fromString')) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Строковый value object должен восстанавливаться из строки.');
            }

            return $this->invokeObjectFactory(rule: $rule, method: 'fromString', value: $value);
        }

        // Инвариант: supportsRule() пропускает ровно четыре вида правил
        // (ColumnValueTypecast, BackedEnum, fromString, fromInt); первые три отсечены выше,
        // поэтому единственное оставшееся правило здесь — фабрика fromInt, и финальный throw
        // означает «не-число для fromInt». Если в supportsRule() добавят новый вид правила,
        // эту хвостовую ветку нужно расширить синхронно, иначе сообщение про «числовой
        // value object» станет вводить в заблуждение.
        if (\is_string($value) && \preg_match(pattern: '/^-?\d+$/', subject: $value) === 1) {
            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: (int) $value);
        }

        if (\is_int($value)) {
            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: $value);
        }

        throw new \InvalidArgumentException('Числовой value object должен восстанавливаться из числа.');
    }

    private function uncastField(
        string $field,
        bool|int|float|string|object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        $rule = $this->rules[$field] ?? null;

        if ($rule !== null) {
            return $this->uncastFieldByRule(rule: $rule, value: $value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (\is_object($value)) {
            throw new \InvalidArgumentException('Объект не поддерживает запись в базу.');
        }

        return $value;
    }

    /**
     * @param class-string $rule
     */
    private function uncastFieldByRule(
        string $rule,
        bool|int|float|string|object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
            if ($value !== null && !\is_object($value)) {
                return $value;
            }

            return $this->invokeColumnUncast(rule: $rule, value: $value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (!\is_object($value)) {
            return $value;
        }

        if (!\method_exists(object_or_class: $value, method: 'value')) {
            throw new \InvalidArgumentException('Value object должен иметь метод value().');
        }

        return $this->valueObjectDatabaseValue($value);
    }

    /**
     * @param class-string $rule
     */
    private function invokeObjectFactory(string $rule, string $method, string|int $value): object
    {
        $createdValue = (new \ReflectionMethod(objectOrMethod: $rule, method: $method))->invoke(null, $value);

        if (!\is_object($createdValue)) {
            throw new \InvalidArgumentException('Фабрика value object должна вернуть объект.');
        }

        return $createdValue;
    }

    /**
     * @param class-string $rule
     */
    private function invokeColumnCast(
        string $rule,
        bool|int|float|string|\DateTimeInterface|null $value,
    ): object {
        $castValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'castDatabaseValue'))->invoke(null, $value);

        if (!\is_object($castValue)) {
            throw new \InvalidArgumentException('Typecast базы должен вернуть объект.');
        }

        return $castValue;
    }

    /**
     * @param class-string $rule
     */
    private function invokeColumnUncast(
        string $rule,
        object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        $databaseValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'uncastValue'))->invoke(null, $value);

        if ($databaseValue === null) {
            return null;
        }

        if (\is_bool($databaseValue) || \is_int($databaseValue) || \is_float($databaseValue) || \is_string($databaseValue)) {
            return $databaseValue;
        }

        if ($databaseValue instanceof \DateTimeInterface) {
            return $databaseValue;
        }

        throw new \InvalidArgumentException('Typecast базы вернул неподдерживаемый тип.');
    }

    private function valueObjectDatabaseValue(object $value): bool|int|float|string|\DateTimeInterface|null

exec
/bin/zsh -lc "sed -n '1,260p' tests/DatabaseTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Spiral\Storage\StorageInterface;
use Tests\Storage\FakeStorage;

/**
 * Базовый класс для обычных DB-тестов.
 *
 * В `setUp()` открывает транзакцию на `DatabaseInterface`, в `tearDown()`
 * откатывает её, поэтому данные не утекают между тестами без ручной очистки
 * таблиц. ORM heap чистится до и после теста, а storage по умолчанию заменён
 * fake-реализацией, чтобы DB-тест не ходил в MinIO.
 *
 * Тесты, которым нужен реальный commit, relay, queue status, console flow или
 * проверка транзакционного поведения, наследуются от
 * `Tests\NonTransactionalDatabaseTestCase`.
 */
abstract class DatabaseTestCase extends TestCase
{
    private DatabaseInterface|null $transactionalDatabase = null;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOrmHeap();

        if ($this->useFakeStorage()) {
            $this->getContainer()->bindSingleton(
                StorageInterface::class,
                new FakeStorage(TestRuntime::storageDirectory($this->rootDirectory())),
            );
        }

        if ($this->useDatabaseTransaction()) {
            $database = $this->getContainer()->get(DatabaseInterface::class);
            $database->begin();
            $this->transactionalDatabase = $database;
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        try {
            if ($this->transactionalDatabase !== null) {
                $this->transactionalDatabase->rollback();
                $this->transactionalDatabase = null;
            }

            $this->cleanOrmHeap();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Обычный DB-тест оборачивается в транзакцию с rollback.
     */
    protected function useDatabaseTransaction(): bool
    {
        return true;
    }

    /**
     * Обычный DB-тест получает fake storage вместо MinIO.
     */
    protected function useFakeStorage(): bool
    {
        return true;
    }

    protected function cleanOrmHeap(): void
    {
        $container = $this->getContainer();

        if ($container->has(EntityManagerInterface::class)) {
            $container->get(EntityManagerInterface::class)->clean();
        }

        if ($container->has(ORMInterface::class)) {
            $container->get(ORMInterface::class)->getHeap()->clean();
        }
    }
}

exec
/bin/zsh -lc "sed -n '620,980p' docs/plans/2026-06-17_14-16_posts-comments-db-layer.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
  - Расхождение порядка `down()` между разделами — фактически порядок один;
    вместо дублирования сделана ссылка на единственный список в «Данные и БД».

exec
/bin/zsh -lc "rg --files app/src/Modules/Media app/src/Modules/User app/src/Modules/Access app/src/Shared/Infrastructure/Cycle app/database/migrations tests | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php
app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php
app/src/Shared/Infrastructure/Cycle/AbstractRepository.php
app/src/Shared/Infrastructure/Cycle/LazyGhostReflectionRegistry.php
app/src/Shared/Infrastructure/Cycle/WhenSelect.php
app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReference.php
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php
app/database/migrations/20260613.143902_0_create_access_domain_tables.php
app/database/migrations/20260616.180010_0_add_device_to_auth_tokens.php
app/database/migrations/20260525.153700_0_create_outbox_events_table.php
app/database/migrations/20260613.143901_0_create_user_domain_tables.php
app/database/migrations/20260521.184100_0_create_media_domain_tables.php
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
app/src/Modules/Access/Domain/Enum/PermissionName.php
app/src/Modules/Access/Domain/Enum/RoleName.php
app/src/Modules/Access/Repository/PermissionRepository.php
app/src/Modules/Access/Repository/UserRoleRepository.php
app/src/Modules/Access/Repository/RoleRepository.php
app/src/Modules/Access/Repository/RolePermissionRepository.php
tests/Kernel/Modules/Auth/AuthBootloaderTest.php
app/src/Modules/Access/Domain/Entity/Permission.php
app/src/Modules/Access/Domain/Entity/UserRole.php
app/src/Modules/Access/Domain/Entity/RolePermission.php
app/src/Modules/Access/Domain/Entity/Role.php
app/src/Modules/User/Application/Dto/UserAuthView.php
app/src/Modules/Access/Domain/Collection/PermissionCollection.php
app/src/Modules/Access/Domain/Collection/RolePermissionCollection.php
app/src/Modules/Access/Domain/Collection/RoleCollection.php
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
app/src/Modules/User/Repository/UserRepository.php
app/src/Modules/User/Repository/UserBanRepository.php
app/src/Modules/User/Repository/ReservedNicknameRepository.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthHandler.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthQuery.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
app/src/Modules/Access/Domain/ValueObject/RolePermissionId.php
app/src/Modules/Access/Domain/ValueObject/PermissionId.php
app/src/Modules/Access/Domain/ValueObject/RoleId.php
app/src/Modules/Access/Domain/ValueObject/PermissionSlug.php
app/src/Modules/Access/Domain/ValueObject/UserRoleId.php
app/src/Modules/Access/Domain/ValueObject/RoleSlug.php
tests/Kernel/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserResult.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserCommand.php
app/src/Modules/Media/README.md
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php
app/src/Modules/User/Domain/Enum/UserStatus.php
app/src/Modules/User/Domain/Enum/UserVerification.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadId.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php
app/src/Modules/Media/Domain/ValueObject/MediaDuration.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php
app/src/Modules/Media/Domain/ValueObject/MediaFileSize.php
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php
app/src/Modules/Media/Domain/ValueObject/MediaVideoConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php
app/src/Modules/Media/Domain/ValueObject/MediaBitrate.php
app/src/Modules/Media/Domain/ValueObject/MediaPixelDimension.php
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php
app/src/Modules/Media/Domain/ValueObject/MediaId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
app/src/Modules/Media/Domain/ValueObject/MediaImageConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartNumber.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
app/src/Modules/User/Domain/Entity/ReservedNickname.php
app/src/Modules/User/Domain/Entity/UserBan.php
app/src/Modules/User/Domain/Entity/User.php
app/src/Modules/Media/Application/Message/MediaUploaded.php
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameId.php
app/src/Modules/User/Domain/ValueObject/UserNickname.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php
app/src/Modules/User/Domain/ValueObject/UserBio.php
app/src/Modules/User/Domain/ValueObject/UserAvatar.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php
app/src/Modules/User/Domain/ValueObject/UserDeletion.php
app/src/Modules/User/Domain/ValueObject/UserName.php
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
app/src/Modules/User/Domain/ValueObject/UserLocation.php
app/src/Modules/User/Domain/ValueObject/UserBanId.php
app/src/Modules/User/Domain/ValueObject/BanReason.php
app/src/Modules/User/Domain/ValueObject/BanExpiration.php
app/src/Modules/User/Domain/ValueObject/Email.php
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php
tests/RealStorageTestCase.php
tests/warmup.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
tests/Storage/FakeStorage.php
tests/bootstrap.php
tests/TestRuntime.php
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php
tests/Kernel/Shared/Infrastructure/Configuration/PushConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/CentrifugoConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
tests/Kernel/DemoTest.php
tests/TestCase.php
tests/DatabaseTestCase.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php
tests/App/TestKernel.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
tests/App/Modules/System/Http/ApiErrorTestController.php
tests/App/Modules/System/Http/ApiErrorTestFilter.php
tests/App/Bootloader/ApiErrorTestRoutesBootloader.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php
tests/Support/Notifications/RecordingOutboxEventStore.php
tests/Support/Notifications/FixtureNotificationTypeDefinition.php
tests/NonTransactionalDatabaseTestCase.php
tests/Feature/CqrsContainerTest.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
tests/Feature/Modules/Notifications/Presentation/DispatchNotificationJobTest.php
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php
tests/Feature/Shared/Infrastructure/DockerRuntimeSmokeTest.php
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
tests/Feature/Modules/System/Console/OpenApiPublishAssetsCommandTest.php
tests/Feature/Modules/System/Console/OpenApiGenerateCommandTest.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php
tests/Feature/Modules/System/Http/OpenApiHttpTest.php
tests/Feature/Modules/System/Http/LocaleHttpTest.php
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php
tests/Feature/Modules/Auth/Infrastructure/SpiralLoginCodeMailerTest.php
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php
tests/Feature/Modules/Outbox/CleansOutboxEvents.php
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php
tests/Feature/Modules/Auth/Application/RequestLoginCodeHandlerTest.php
tests/Feature/Modules/Auth/Application/RevokeUserSessionHandlerTest.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php
tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php
tests/Unit/Modules/Access/Domain/AccessDomainTest.php
tests/Feature/Modules/Auth/Application/Fixture/RecordingLoginCodeMailer.php
tests/Feature/Modules/Auth/Application/Fixture/RecordingMailer.php
tests/Feature/Modules/Auth/Application/Fixture/RecordingOutboxEventStore.php
tests/Feature/Modules/Auth/Application/Fixture/FakeSecretHasher.php
tests/Feature/Modules/Auth/Application/Fixture/SentLoginCodeEmail.php
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php
tests/Unit/Modules/Notifications/Application/NotificationSenderTest.php
tests/Unit/Modules/Notifications/Application/NotificationMessageSerializerTest.php
tests/Unit/Modules/Notifications/Application/PublishRealtimeNotificationHandlerTest.php

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Infrastructure/Cycle/AbstractRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use Cycle\ORM\ORM;
use Cycle\ORM\Select;
use Cycle\ORM\Select\Repository;

/**
 * Базовый репозиторий, чей select() возвращает WhenSelect с методом when().
 *
 * Cycle жёстко создаёт в RepositoryProvider плоский Select, поэтому WhenSelect строим сами
 * из orm+role и кладём базовым select-ом репозитория. Тогда select(), repo-forUpdate() и
 * __clone() работают на WhenSelect.
 *
 * @template TEntity of object
 *
 * @extends Repository<TEntity>
 *
 * @method WhenSelect<TEntity> select()
 */
abstract class AbstractRepository extends Repository
{
    /**
     * @param Select<TEntity> $select
     */
    public function __construct(Select $select, ORM $orm, string $role)
    {
        parent::__construct($select);

        /** @var WhenSelect<TEntity> $whenSelect */
        $whenSelect = new WhenSelect(orm: $orm, role: $role);
        // Scope источника — как в RepositoryProvider (scoped-сущностей сейчас нет → no-op,
        // строка ради совместимости на будущее).
        $whenSelect->scope($orm->getSource($role)->getScope());

        // Метка @readonly на родительском $select — это PHPDoc, а не настоящий readonly PHP:
        // в рантайме такая перезапись разрешена (проверено на PHP 8.5). Подменяем плоский
        // Select на WhenSelect, чтобы базовым запросом репозитория стал именно он.
        // @phpstan-ignore property.readOnlyByPhpDocAssignOutOfClass
        $this->select = $whenSelect;
    }
}

codex
Нашёл важный технический нюанс: `ValueObjectCast` действительно выбирает `fromString` раньше `fromInt`, и план это уже учитывает для счётчиков. Теперь проверяю сами сущности, миграции и репозитории, чтобы отделить уже закрытые риски от оставшихся.
exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Media/Domain/Entity/MediaImageConversion.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaImageConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_image_conversion',
    table: 'media_image_conversions',
    repository: MediaImageConversionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class MediaImageConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaImageConversionId::class)]
    public private(set) MediaImageConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaImageConversionType::class)]
    public private(set) MediaImageConversionType $type;

    #[Column(type: 'string(32)', typecast: MediaConversionStatus::class)]
    public private(set) MediaConversionStatus $status;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $width;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $height;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

    public static function create(
        Media $media,
        MediaImageConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
    ): self {
        $conversion = new self();
        $conversion->id = MediaImageConversionId::generate();
        $conversion->media = $media;
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->initializeTimestamps();

        return $conversion;
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Domain/Entity/Media.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Infrastructure\Cycle\MediaExpirationTypecast;
use App\Modules\Media\Infrastructure\Cycle\MediaProcessingErrorTypecast;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media',
    table: 'media',
    repository: MediaRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Media
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaId::class)]
    public private(set) MediaId $id;

    #[Column(type: 'uuid', name: 'storage_key', typecast: MediaStorageKey::class)]
    public private(set) MediaStorageKey $storageKey;

    #[Column(type: 'string(32)', typecast: MediaType::class)]
    public private(set) MediaType $type;

    #[Column(type: 'string(64)', typecast: MediaStatus::class)]
    public private(set) MediaStatus $status;

    #[Column(type: 'string(16)', typecast: MediaVisibility::class)]
    public private(set) MediaVisibility $visibility;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'uuid', name: 'uploaded_by_id', typecast: UserId::class)]
    public private(set) UserId $uploadedById;

    #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: MediaExpirationTypecast::class)]
    public private(set) MediaExpiration $expiration;

    #[Column(type: 'integer', name: 'processing_attempts', typecast: MediaProcessingAttempts::class)]
    public private(set) MediaProcessingAttempts $processingAttempts;

    #[Column(type: 'text', name: 'processing_error', nullable: true, typecast: MediaProcessingErrorTypecast::class)]
    public private(set) MediaProcessingError $processingError;

    #[HasMany(
        target: MediaImageConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaImageConversionCollection::class,
    )]
    public private(set) MediaImageConversionCollection $imageConversions;

    #[HasMany(
        target: MediaVideoConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaVideoConversionCollection::class,
    )]
    public private(set) MediaVideoConversionCollection $videoConversions;

    public static function create(
        MediaStorageKey $storageKey,
        MediaType $type,
        MediaVisibility $visibility,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        UserId $uploadedById,
        MediaExpiration $expiration,
    ): self {
        $media = new self();
        $media->id = MediaId::generate();
        $media->storageKey = $storageKey;
        $media->type = $type;
        $media->status = MediaStatus::WaitingUpload;
        $media->visibility = $visibility;
        $media->storage = MediaStorage::Upload;
        $media->path = $path;
        $media->mimeType = $mimeType;
        $media->size = $size;
        $media->uploadedById = $uploadedById;
        $media->expiration = $expiration;
        $media->processingAttempts = MediaProcessingAttempts::zero();
        $media->processingError = MediaProcessingError::none();
        $media->imageConversions = new MediaImageConversionCollection();
        $media->videoConversions = new MediaVideoConversionCollection();
        $media->initializeTimestamps();

        return $media;
    }

    public function startCompletingMultipartUpload(): void
    {
        $this->status = MediaStatus::CompletingMultipartUpload;
        $this->touch();
    }

    public function markMultipartCompletionFailedCanRetry(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedCanRetry;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markMultipartCompletionFailedNeedReupload(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedNeedReupload;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markUploaded(): void
    {
        $this->status = MediaStatus::Uploaded;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function startProcessing(): void
    {
        $this->status = MediaStatus::Processing;
        $this->touch();
    }

    /**
     * Готовое медиа не «ломается» задним числом: повторная/запоздалая фиксация ошибки на уже
     * ready-медиа — no-op (симметрично guard'у в markReadyMovedTo). Защищает инвариант
     * «ready без ошибки» независимо от вызывающего, даже если фиксацию сбоя задиспатчат в обход
     * isReady-guard'а в ProcessMediaHandler (другой relay, ручной перезапуск Job, дубликат в очереди).
     */
    public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        $this->status = MediaStatus::ProcessingFailed;
        $this->processingAttempts = $this->processingAttempts->increment();
        $this->processingError = $processingError;
        $this->touch();
    }

    /**
     * См. recordTemporaryProcessingError: тот же инвариант «ready без ошибки» — на уже
     * ready-медиа фиксация постоянной ошибки также no-op.
     */
    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        $this->status = MediaStatus::ProcessingFailed;
        $this->processingAttempts = $this->processingAttempts->increment();
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markReady(): void
    {
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    /**
     * Перевод в ready с переназначением целевого хранилища и пути (после перекладки оригинала
     * из staging). Идемпотентен: повторная доставка на ready — no-op. Допустим из uploaded,
     * processing или processingFailed (ретрай обработки после транзиентной ошибки).
     */
    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        if (
            $this->status !== MediaStatus::Uploaded
            && $this->status !== MediaStatus::Processing
            && $this->status !== MediaStatus::ProcessingFailed
        ) {
            throw new InvalidDomainValueException(
                'Перевод медиа в ready допустим только из uploaded, processing или processingFailed.',
            );
        }

        $this->storage = $storage;
        $this->path = $path;
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    public function markReadyOriginalRemoved(): void
    {
        $this->status = MediaStatus::ReadyOriginalRemoved;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function makePermanent(): void
    {
        $this->expiration = MediaExpiration::permanent();
        $this->touch();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/User/Domain/Entity/User.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string(100)', typecast: UserName::class)]
    public private(set) UserName $name;

    #[Column(type: 'string(100)', name: 'spiritual_name', nullable: true, typecast: UserSpiritualNameTypecast::class)]
    public private(set) UserSpiritualName $spiritualName;

    #[Column(type: 'text', nullable: true, typecast: UserBioTypecast::class)]
    public private(set) UserBio $bio;

    #[Column(type: 'string(100)', nullable: true, typecast: UserLocationTypecast::class)]
    public private(set) UserLocation $location;

    #[Column(type: 'string(254)', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    public private(set) UserNickname $nickname;

    #[Column(type: 'uuid', name: 'avatar_media_id', nullable: true, typecast: UserAvatarTypecast::class)]
    public private(set) UserAvatar $avatar;

    #[Column(type: 'string(32)', typecast: UserVerification::class)]
    public private(set) UserVerification $verification;

    #[Column(type: 'string(32)', typecast: UserStatus::class)]
    public private(set) UserStatus $status;

    #[Column(type: 'string(8)', typecast: Locale::class)]
    public private(set) Locale $locale;

    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
    public private(set) UserDeletion $deletion;

    public static function create(
        UserName $name,
        Email $email,
        UserNickname $nickname,
        Locale $locale,
    ): self {
        $user = new self();
        $user->id = UserId::generate();
        $user->name = $name;
        $user->spiritualName = UserSpiritualName::none();
        $user->bio = UserBio::none();
        $user->location = UserLocation::none();
        $user->email = $email;
        $user->nickname = $nickname;
        $user->avatar = UserAvatar::none();
        $user->verification = UserVerification::Unverified;
        $user->status = UserStatus::WaitingEmailConfirmation;
        $user->locale = $locale;
        $user->deletion = UserDeletion::active();
        $user->initializeTimestamps();

        return $user;
    }

    public function confirmEmail(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
        $this->status = UserStatus::WaitingEmailConfirmation;
        $this->touch();
    }

    public function changeNickname(UserNickname $nickname): void
    {
        $this->nickname = $nickname;
        $this->touch();
    }

    public function rename(UserName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeSpiritualName(UserSpiritualName $spiritualName): void
    {
        $this->spiritualName = $spiritualName;
        $this->touch();
    }

    public function changeBio(UserBio $bio): void
    {
        $this->bio = $bio;
        $this->touch();
    }

    public function changeLocation(UserLocation $location): void
    {
        $this->location = $location;
        $this->touch();
    }

    public function changeLocale(Locale $locale): void
    {
        $this->locale = $locale;
        $this->touch();
    }

    public function setAvatar(UserAvatar $avatar): void
    {
        $this->avatar = $avatar;
        $this->touch();
    }

    public function removeAvatar(): void
    {
        $this->avatar = UserAvatar::none();
        $this->touch();
    }

    public function verify(): void
    {
        $this->verification = UserVerification::Verified;
        $this->touch();
    }

    public function unverify(): void
    {
        $this->verification = UserVerification::Unverified;
        $this->touch();
    }

    public function ban(): void
    {
        $this->status = UserStatus::Banned;
        $this->touch();
    }

    public function unban(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletion = UserDeletion::at($now);
        $this->status = UserStatus::Deleted;
        $this->touch(now: $now);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isVerified(): bool
    {
        return $this->verification === UserVerification::Verified;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned;
    }

    public function isDeleted(): bool
    {
        return $this->status === UserStatus::Deleted || $this->deletion->isDeleted();
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Access/Domain/Entity/RolePermission.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RolePermissionId;
use App\Modules\Access\Repository\RolePermissionRepository;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'role_permission',
    table: 'role_permissions',
    repository: RolePermissionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class RolePermission
{
    #[Column(type: 'uuid', primary: true, typecast: RolePermissionId::class)]
    public private(set) RolePermissionId $id;

    #[Column(type: 'uuid', name: 'role_id', typecast: RoleId::class)]
    public private(set) RoleId $roleId;

    #[Column(type: 'uuid', name: 'permission_id', typecast: PermissionId::class)]
    public private(set) PermissionId $permissionId;

    public static function create(RoleId $roleId, PermissionId $permissionId): self
    {
        $rolePermission = new self();
        $rolePermission->id = RolePermissionId::generate();
        $rolePermission->roleId = $roleId;
        $rolePermission->permissionId = $permissionId;

        return $rolePermission;
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/User/Domain/Entity/UserBan.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Modules\User\Infrastructure\Cycle\BanExpirationTypecast;
use App\Modules\User\Infrastructure\Cycle\BanUnbannedAtTypecast;
use App\Modules\User\Infrastructure\Cycle\BanUnbannedByTypecast;
use App\Modules\User\Infrastructure\Cycle\BanUnbannedReasonTypecast;
use App\Modules\User\Repository\UserBanRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_ban',
    table: 'user_bans',
    repository: UserBanRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class UserBan
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserBanId::class)]
    public private(set) UserBanId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'banned_by_id', typecast: UserId::class)]
    public private(set) UserId $bannedById;

    #[Column(type: 'string(500)', typecast: BanReason::class)]
    public private(set) BanReason $reason;

    #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: BanExpirationTypecast::class)]
    public private(set) BanExpiration $expiration;

    #[Column(type: 'datetime', name: 'unbanned_at', nullable: true, typecast: BanUnbannedAtTypecast::class)]
    public private(set) BanUnbannedAt $unbannedAt;

    #[Column(type: 'uuid', name: 'unbanned_by_id', nullable: true, typecast: BanUnbannedByTypecast::class)]
    public private(set) BanUnbannedBy $unbannedBy;

    #[Column(type: 'string(500)', name: 'unbanned_reason', nullable: true, typecast: BanUnbannedReasonTypecast::class)]
    public private(set) BanUnbannedReason $unbannedReason;

    public static function create(
        UserId $userId,
        UserId $bannedById,
        BanReason $reason,
        BanExpiration $expiration,
    ): self {
        $userBan = new self();
        $userBan->id = UserBanId::generate();
        $userBan->userId = $userId;
        $userBan->bannedById = $bannedById;
        $userBan->reason = $reason;
        $userBan->expiration = $expiration;
        $userBan->unbannedAt = BanUnbannedAt::notUnbanned();
        $userBan->unbannedBy = BanUnbannedBy::none();
        $userBan->unbannedReason = BanUnbannedReason::none();
        $userBan->initializeTimestamps();

        return $userBan;
    }

    public function markUnbanned(
        BanUnbannedBy $unbannedBy,
        BanUnbannedAt $unbannedAt,
        BanUnbannedReason $unbannedReason,
    ): void {
        $this->unbannedBy = $unbannedBy;
        $this->unbannedAt = $unbannedAt;
        $this->unbannedReason = $unbannedReason;
        $this->touch();
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return !$this->unbannedAt->isUnbanned() && !$this->expiration->isExpired($now);
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Access/Domain/Entity/UserRole.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\UserRoleId;
use App\Modules\Access\Repository\UserRoleRepository;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_role',
    table: 'user_roles',
    repository: UserRoleRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class UserRole
{
    #[Column(type: 'uuid', primary: true, typecast: UserRoleId::class)]
    public private(set) UserRoleId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'role_id', typecast: RoleId::class)]
    public private(set) RoleId $roleId;

    public static function create(UserId $userId, RoleId $roleId): self
    {
        $userRole = new self();
        $userRole->id = UserRoleId::generate();
        $userRole->userId = $userId;
        $userRole->roleId = $roleId;

        return $userRole;
    }
}

codex
По эталонам вижу расхождение с планом: существующие плоские таблицы связей `Access` не имеют `created_at/updated_at`, а план хочет добавить их почти во все такие таблицы. Это не обязательно ошибка, но это надо явно считать решением, а не “по образцу”.
exec
/bin/zsh -lc "sed -n '1,260p' app/database/migrations/20260613.143901_0_create_user_domain_tables.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateUserDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('users')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
            ->addColumn('spiritual_name', 'string', ['length' => 100, 'nullable' => true])
            ->addColumn('bio', 'text', ['nullable' => true])
            ->addColumn('location', 'string', ['length' => 100, 'nullable' => true])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
            ->addColumn('avatar_media_id', 'uuid', ['nullable' => true])
            ->addColumn('verification', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['nickname'], ['unique' => true])
            ->addIndex(['status'])
            ->addForeignKey(
                ['avatar_media_id'],
                'media',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('user_bans')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('banned_by_id', 'uuid', ['nullable' => false])
            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => true])
            ->addColumn('unbanned_at', 'datetime', ['nullable' => true])
            ->addColumn('unbanned_by_id', 'uuid', ['nullable' => true])
            ->addColumn('unbanned_reason', 'string', ['length' => 500, 'nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['user_id'])
            ->create();

        $this->table('reserved_nicknames')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
            ->addColumn('assigned_user_id', 'uuid', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['nickname'], ['unique' => true])
            ->addForeignKey(
                ['assigned_user_id'],
                'users',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['assigned_user_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('reserved_nicknames')->drop();
        $this->table('user_bans')->drop();
        $this->table('users')->drop();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/database/migrations/20260613.143902_0_create_access_domain_tables.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateAccessDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('roles')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('permissions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('role_permissions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('role_id', 'uuid', ['nullable' => false])
            ->addColumn('permission_id', 'uuid', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['role_id'],
                'roles',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['permission_id'],
                'permissions',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
            ->create();

        $this->table('user_roles')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('role_id', 'uuid', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['role_id'],
                'roles',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['user_id', 'role_id'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('user_roles')->drop();
        $this->table('role_permissions')->drop();
        $this->table('permissions')->drop();
        $this->table('roles')->drop();
    }
}

exec
/bin/zsh -lc "sed -n '1,200p' app/database/migrations/20260616.180010_0_add_device_to_auth_tokens.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class AddDeviceToAuthTokens extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('auth_tokens')
            ->addColumn('ip', 'string', ['length' => 45, 'nullable' => true])
            ->addColumn('user_agent', 'text', ['nullable' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('auth_tokens')
            ->dropColumn('ip')
            ->dropColumn('user_agent')
            ->update();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Repository/MediaRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<Media>
 */
final class MediaRepository extends AbstractRepository
{
    public function findById(MediaId $mediaId): Media|null
    {
        return $this->findByPK($mediaId->value());
    }

    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
    {
        return $this->findOne(['storage_key' => $storageKey->value()]);
    }

    public function findExpired(\DateTimeImmutable $now): MediaCollection
    {
        return new MediaCollection(
            $this->select()
                ->where('expires_at', '<=', $now)
                ->fetchAll(),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,300p' app/database/migrations/20260521.184100_0_create_media_domain_tables.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateMediaDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('media')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('storage_key', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('visibility', 'string', ['length' => 16, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('uploaded_by_id', 'uuid', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => true])
            ->addColumn('processing_attempts', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('processing_error', 'text', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['storage_key'], ['unique' => true])
            ->addIndex(['uploaded_by_id'])
            ->addIndex(['status'])
            ->addIndex(['expires_at'])
            ->create();

        $this->table('media_image_conversions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('width', 'integer', ['nullable' => false])
            ->addColumn('height', 'integer', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['media_id', 'type'], ['unique' => true])
            ->addIndex(['media_id'])
            ->addIndex(['status'])
            ->create();

        $this->table('media_video_conversions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('width', 'integer', ['nullable' => false])
            ->addColumn('height', 'integer', ['nullable' => false])
            ->addColumn('duration_ms', 'bigInteger', ['nullable' => false])
            ->addColumn('bitrate', 'integer', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['media_id', 'type'], ['unique' => true])
            ->addIndex(['media_id'])
            ->addIndex(['status'])
            ->create();

        $this->table('media_multipart_uploads')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('upload_id', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('parts_count', 'integer', ['nullable' => false])
            ->addColumn('part_size', 'bigInteger', ['nullable' => false])
            ->addColumn('file_size', 'bigInteger', ['nullable' => false])
            ->addColumn('parts', 'json', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['media_id'], ['unique' => true])
            ->addIndex(['upload_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('media_multipart_uploads')->drop();
        $this->table('media_video_conversions')->drop();
        $this->table('media_image_conversions')->drop();
        $this->table('media')->drop();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/User/Repository/UserRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findById(UserId $userId): User|null
    {
        return $this->findByPK($userId->value());
    }

    public function findByEmail(Email $email): User|null
    {
        return $this->findOne(['email' => $email->value()]);
    }

    public function findByNickname(UserNickname $nickname): User|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function existsByNickname(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}

codex
Проверка миграций подтверждает ещё один момент: ссылка плана на `->update()` как прецедент верная только для добавления колонок, но не для добавления внешнего ключа к уже созданной таблице. Это не блокер, если сначала проверить миграцию, но критерий готовности лучше сделать жёстче.
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Shared/Domain/ValueObject/AbstractUuidV7Id.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

abstract readonly class AbstractUuidV7Id implements \Stringable, \JsonSerializable
{
    final protected function __construct(
        private string $value,
    ) {}

    public static function generate(): static
    {
        return new static(value: Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        self::assertUuidV7($value);

        return new static(value: \strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public static function isUuidV7(string $value): bool
    {
        return Uuid::isValid($value) && Uuid::fromString($value)->getVersion() === 7;
    }

    private static function assertUuidV7(string $value): void
    {
        if (!self::isUuidV7($value)) {
            throw new InvalidDomainValueException('Идентификатор должен быть UUID v7.');
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/User/Domain/ValueObject/UserBio.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserBio implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 500;

    private function __construct(
        private string|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function fromString(string $value): self
    {
        $bio = \trim($value);

        if ($bio === '' || \mb_strlen($bio) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Описание имеет неверную длину.');
        }

        if (\preg_match(pattern: '/(?!\n)\p{Cc}/u', subject: $bio) === 1) {
            throw new InvalidDomainValueException('Описание содержит управляющие символы.');
        }

        return new self(value: $bio);
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function equals(self $bio): bool
    {
        return $this->value === $bio->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->value;
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;
use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaProcessingAttempts extends AbstractIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = 100;
    protected const string NAME = 'Количество попыток обработки';

    public static function zero(): self
    {
        return self::fromInt(0);
    }

    public function increment(): self
    {
        if ($this->value() >= self::MAX) {
            throw new InvalidDomainValueException('Количество попыток обработки превышено.');
        }

        return self::fromInt($this->value() + 1);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

abstract readonly class AbstractIntegerValue implements \Stringable, \JsonSerializable
{
    protected const int MIN = 0;
    protected const int MAX = 0;
    protected const string NAME = 'Значение';

    final protected function __construct(
        private int $value,
    ) {}

    public static function fromInt(int $value): static
    {
        static::assertInRange($value);

        return new static(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Проверяет, попадает ли значение в допустимый доменный диапазон [MIN; MAX] без создания VO.
     * Нужно на границе Application, чтобы перевести невалидный вход в ValidationException (422)
     * до построения VO, которое бросило бы InvalidDomainValueException (500).
     */
    public static function supports(int $value): bool
    {
        return $value >= static::MIN && $value <= static::MAX;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->value;
    }

    #[\Override]
    public function jsonSerialize(): int
    {
        return $this->value;
    }

    protected static function assertInRange(int $value): void
    {
        if ($value < static::MIN || $value > static::MAX) {
            throw new InvalidDomainValueException(
                \sprintf('%s должно быть от %d до %d.', static::NAME, static::MIN, static::MAX),
            );
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Cycle;

use App\Modules\User\Domain\ValueObject\UserBio;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class UserBioTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): UserBio
    {
        if ($value === null) {
            return UserBio::none();
        }

        return UserBio::fromString($value);
    }

    public static function uncastValue(UserBio|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaExpirationTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|\DateTimeInterface|null $value,
    ): MediaExpiration {
        if ($value === null) {
            return MediaExpiration::permanent();
        }

        if ($value instanceof \DateTimeImmutable) {
            return MediaExpiration::temporaryUntil($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return MediaExpiration::temporaryUntil(\DateTimeImmutable::createFromInterface($value));
        }

        return MediaExpiration::temporaryUntil(new \DateTimeImmutable($value));
    }

    public static function uncastValue(
        MediaExpiration|null $value,
    ): \DateTimeImmutable|null {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}

exec
/bin/zsh -lc "sed -n '1,320p' tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class MediaRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresMediaWithValueObjects(): void
    {
        $media = $this->createMedia();

        $this->entityManager()->persist($media);
        $this->entityManager()->run();

        $restoredMedia = $this->mediaRepository()->findById($media->id);

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertTrue($media->id->equals($restoredMedia->id));
        self::assertTrue($media->storageKey->equals($restoredMedia->storageKey));
        self::assertSame(MediaStatus::WaitingUpload, $restoredMedia->status);
        self::assertSame(MediaStorage::Upload, $restoredMedia->storage);
        self::assertTrue($media->uploadedById->equals($restoredMedia->uploadedById));
        self::assertInstanceOf(Media::class, $this->mediaRepository()->findByStorageKey($media->storageKey));
    }

    public function testStoresAndRestoresConversionsAndMultipartUpload(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $imageConversions = $this->imageConversionRepository()->findByMediaId($media->id);
        $videoConversions = $this->videoConversionRepository()->findByMediaId($media->id);
        $restoredMultipartUpload = $this->multipartUploadRepository()->findByMediaId($media->id);

        self::assertInstanceOf(MediaImageConversionCollection::class, $imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $videoConversions);
        self::assertCount(1, $imageConversions);
        self::assertCount(1, $videoConversions);
        self::assertInstanceOf(MediaMultipartUpload::class, $restoredMultipartUpload);
        self::assertInstanceOf(MediaMultipartPartCollection::class, $restoredMultipartUpload->parts);
        self::assertSame(1, $restoredMultipartUpload->parts->first()->partNumber->value());
        self::assertSame('first', $restoredMultipartUpload->parts->first()->eTag->value());
    }

    public function testLazyGhostMapperRestoresRelationsAndKeepsThemAfterSave(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmHeap();

        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();

        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->cleanOrmHeap();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertTrue($mediaId->equals($restoredMedia->id));
        self::assertInstanceOf(MediaImageConversionCollection::class, $restoredMedia->imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $restoredMedia->videoConversions);
        self::assertCount(1, $restoredMedia->imageConversions);
        self::assertCount(1, $restoredMedia->videoConversions);

        $restoredMedia->markReady();
        $this->entityManager()->persist($restoredMedia);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $savedMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $savedMedia);
        self::assertSame(MediaStatus::Ready, $savedMedia->status);
        self::assertCount(1, $savedMedia->imageConversions);
        self::assertCount(1, $savedMedia->videoConversions);
    }

    public function testLazyGhostMapperSavesMediaWithoutReadingRelations(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmHeap();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        $restoredMedia->markReady();
        $this->entityManager()->persist($restoredMedia);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $savedMedia = $this->mediaRepository()->findById($mediaId);
        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();

        self::assertInstanceOf(Media::class, $savedMedia);
        self::assertSame(MediaStatus::Ready, $savedMedia->status);
        self::assertInstanceOf(MediaImageConversionCollection::class, $savedMedia->imageConversions);
        self::assertCount(1, $savedMedia->imageConversions);
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertTrue($mediaId->equals($savedImageConversion->mediaId));
    }

    public function testLazyGhostMapperKeepsBelongsToRelationAfterReadingAndSavingConversion(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmHeap();

        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $restoredImageConversion);
        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));

        $this->entityManager()->persist($restoredImageConversion);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertInstanceOf(Media::class, $savedImageConversion->media);
        self::assertTrue($mediaId->equals($savedImageConversion->media->id));
    }

    public function testFindExpiredReturnsTypedCollection(): void
    {
        $media = $this->createMedia(
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('-1 hour')),
        );

        $this->entityManager()->persist($media);
        $this->entityManager()->run();

        $expiredMedia = $this->mediaRepository()->findExpired(new \DateTimeImmutable());

        self::assertTrue($expiredMedia->contains(static fn(Media $expired) => $expired->id->equals($media->id)));
    }

    public function testStorageKeyIsUnique(): void
    {
        $storageKey = MediaStorageKey::generate();

        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));
        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCascadeDeletesRelatedRows(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($imageConversion);
        $this->entityManager()->persist($videoConversion);
        $this->entityManager()->persist($multipartUpload);
        $this->entityManager()->run();

        $this->entityManager()->delete($media);
        $this->entityManager()->run();

        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
        self::assertNull($this->multipartUploadRepository()->findByMediaId($media->id));
    }

    private function createMedia(
        MediaStorageKey|null $storageKey = null,
        MediaExpiration|null $expiration = null,
    ): Media {
        $storageKey ??= MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: $expiration ?? MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    private function createImageConversion(Media $media): MediaImageConversion
    {
        $storageKey = MediaStorageKey::generate();

        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('images/%s/%s/thumbnail.jpg', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(512),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    private function createVideoConversion(Media $media): MediaVideoConversion
    {
        $storageKey = MediaStorageKey::generate();

        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('videos/%s/%s/normalized.mp4', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(2048),
            width: MediaPixelDimension::fromInt(1920),
            height: MediaPixelDimension::fromInt(1080),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(5000),
        );
    }

    private function createMultipartUpload(Media $media): MediaMultipartUpload
    {
        $multipartUpload = MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: MediaFileSize::fromInt(5_242_880),
        );
        $multipartUpload->replaceParts(new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('first'),
            ),
        ]));

        return $multipartUpload;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

exec
/bin/zsh -lc "sed -n '1,320p' tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Repository\PermissionRepository;
use App\Modules\Access\Repository\RolePermissionRepository;
use App\Modules\Access\Repository\RoleRepository;
use App\Modules\Access\Repository\UserRoleRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class AccessRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndFindsRolesAndPermissions(): void
    {
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));

        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertInstanceOf(Role::class, $this->roleRepository()->findById($role->id));
        self::assertInstanceOf(Role::class, $this->roleRepository()->findBySlug(RoleSlug::fromString('admin')));
        self::assertInstanceOf(Permission::class, $this->permissionRepository()->findById($permission->id));
        self::assertInstanceOf(
            Permission::class,
            $this->permissionRepository()->findBySlug(PermissionSlug::fromString('user.ban')),
        );

        $rolesByIds = $this->roleRepository()->findByIds($role->id);
        $permissionsByIds = $this->permissionRepository()->findByIds($permission->id);

        self::assertInstanceOf(RoleCollection::class, $rolesByIds);
        self::assertCount(1, $rolesByIds);
        self::assertInstanceOf(PermissionCollection::class, $permissionsByIds);
        self::assertCount(1, $permissionsByIds);
        self::assertInstanceOf(RoleCollection::class, $this->roleRepository()->findAll());
        self::assertCount(1, $this->roleRepository()->findAll());
        self::assertInstanceOf(PermissionCollection::class, $this->permissionRepository()->findAll());
        self::assertCount(1, $this->permissionRepository()->findAll());
    }

    public function testStoresAndFindsLinks(): void
    {
        $user = $this->createUser();
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));
        $rolePermission = RolePermission::create(roleId: $role->id, permissionId: $permission->id);
        $userRole = UserRole::create(userId: $user->id, roleId: $role->id);

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->persist($rolePermission);
        $this->entityManager()->persist($userRole);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $rolePermissions = $this->rolePermissionRepository()->findByRoleId($role->id);
        $userRoles = $this->userRoleRepository()->findByUserId($user->id);

        self::assertInstanceOf(RolePermissionCollection::class, $rolePermissions);
        self::assertCount(1, $rolePermissions);
        self::assertTrue($this->rolePermissionRepository()->exists(roleId: $role->id, permissionId: $permission->id));
        self::assertInstanceOf(UserRoleCollection::class, $userRoles);
        self::assertCount(1, $userRoles);
        self::assertTrue($this->userRoleRepository()->exists(userId: $user->id, roleId: $role->id));
    }

    public function testDuplicateRoleSlugFails(): void
    {
        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));
        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicatePermissionSlugFails(): void
    {
        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));
        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateRolePermissionFails(): void
    {
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));

        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));
        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateUserRoleFails(): void
    {
        $user = $this->createUser();
        $role = Role::create(RoleSlug::fromString('admin'));

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($role);
        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));
        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function createUser(): User
    {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('access@example.com'),
            nickname: UserNickname::fromString('access.user'),
            locale: Locale::Ru,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function roleRepository(): RoleRepository
    {
        return $this->getContainer()->get(RoleRepository::class);
    }

    private function permissionRepository(): PermissionRepository
    {
        return $this->getContainer()->get(PermissionRepository::class);
    }

    private function rolePermissionRepository(): RolePermissionRepository
    {
        return $this->getContainer()->get(RolePermissionRepository::class);
    }

    private function userRoleRepository(): UserRoleRepository
    {
        return $this->getContainer()->get(UserRoleRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/User/Repository/UserRepositoryTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Repository;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserBanRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresUserWithValueObjects(): void
    {
        $media = $this->createMedia();
        $user = $this->createUser();
        $user->changeSpiritualName(UserSpiritualName::fromString('Шанти'));
        $user->changeBio(UserBio::fromString('Описание'));
        $user->changeLocation(UserLocation::fromString('Москва'));
        $user->setAvatar(UserAvatar::pointingTo($media->id->value()));
        $user->confirmEmail();
        $user->markDeleted(new \DateTimeImmutable('2026-06-13 12:00:00'));

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($user);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredUser = $this->userRepository()->findById($user->id);

        self::assertInstanceOf(User::class, $restoredUser);
        self::assertTrue($user->id->equals($restoredUser->id));
        self::assertSame('test@example.com', $restoredUser->email->value());
        self::assertSame('yoga.test', $restoredUser->nickname->value());
        self::assertSame('Шанти', $restoredUser->spiritualName->value());
        self::assertSame('Описание', $restoredUser->bio->value());
        self::assertSame('Москва', $restoredUser->location->value());
        self::assertSame($media->id->value(), $restoredUser->avatar->value());
        self::assertTrue($restoredUser->isDeleted());
        self::assertSame(UserStatus::Deleted, $restoredUser->status);
        self::assertSame(Locale::Ru, $restoredUser->locale);
        self::assertInstanceOf(User::class, $this->userRepository()->findByEmail(Email::fromString('TEST@example.com')));
        self::assertInstanceOf(User::class, $this->userRepository()->findByNickname(UserNickname::fromString('YOGA.TEST')));
        self::assertTrue($this->userRepository()->existsByEmail(Email::fromString('test@example.com')));
        self::assertTrue($this->userRepository()->existsByNickname(UserNickname::fromString('yoga.test')));
    }

    public function testStoresAndRestoresUserWithEmptyOptionalValues(): void
    {
        $user = $this->createUser(email: 'empty@example.com', nickname: 'empty.user');

        $this->entityManager()->persist($user);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredUser = $this->userRepository()->findById($user->id);

        self::assertInstanceOf(User::class, $restoredUser);
        self::assertTrue($restoredUser->spiritualName->isEmpty());
        self::assertTrue($restoredUser->bio->isEmpty());
        self::assertTrue($restoredUser->location->isEmpty());
        self::assertTrue($restoredUser->avatar->isEmpty());
        self::assertFalse($restoredUser->deletion->isDeleted());
    }

    public function testStoresAndFindsActiveUserBans(): void
    {
        $user = $this->createUser();
        $permanentBan = UserBan::create(
            userId: $user->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Навсегда'),
            expiration: BanExpiration::permanent(),
        );

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($permanentBan);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredBan = $this->userBanRepository()->findById($permanentBan->id);
        $activeBan = $this->userBanRepository()->findActiveByUserId($user->id, new \DateTimeImmutable());

        self::assertInstanceOf(UserBan::class, $restoredBan);
        self::assertInstanceOf(UserBan::class, $activeBan);
        self::assertTrue($permanentBan->id->equals($activeBan->id));
    }

    public function testFindActiveUserBanIgnoresExpiredAndUnbannedRows(): void
    {
        $expiredUser = $this->createUser(email: 'expired@example.com', nickname: 'expired.user');
        $unbannedUser = $this->createUser(email: 'unbanned@example.com', nickname: 'unbanned.user');
        $activeTemporaryUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
        $expiredBan = UserBan::create(
            userId: $expiredUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Истёк'),
            expiration: BanExpiration::until(new \DateTimeImmutable('-1 hour')),
        );
        $unbannedBan = UserBan::create(
            userId: $unbannedUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Снят'),
            expiration: BanExpiration::permanent(),
        );
        $activeTemporaryBan = UserBan::create(
            userId: $activeTemporaryUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Активен'),
            expiration: BanExpiration::until(new \DateTimeImmutable('+1 hour')),
        );
        $unbannedBan->markUnbanned(
            unbannedBy: BanUnbannedBy::by(UserId::generate()),
            unbannedAt: BanUnbannedAt::at(new \DateTimeImmutable()),
            unbannedReason: BanUnbannedReason::of('Снят'),
        );

        $this->entityManager()->persist($expiredUser);
        $this->entityManager()->persist($unbannedUser);
        $this->entityManager()->persist($activeTemporaryUser);
        $this->entityManager()->persist($expiredBan);
        $this->entityManager()->persist($unbannedBan);
        $this->entityManager()->persist($activeTemporaryBan);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertNull($this->userBanRepository()->findActiveByUserId($expiredUser->id, new \DateTimeImmutable()));
        self::assertNull($this->userBanRepository()->findActiveByUserId($unbannedUser->id, new \DateTimeImmutable()));
        self::assertInstanceOf(
            UserBan::class,
            $this->userBanRepository()->findActiveByUserId($activeTemporaryUser->id, new \DateTimeImmutable()),
        );
    }

    public function testStoresReservedNicknameWithHolder(): void
    {
        $user = $this->createUser();
        $reservedNickname = ReservedNickname::create(UserNickname::fromString('reserved'));
        $reservedNickname->assignTo($user->id);

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($reservedNickname);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredReservedNickname = $this->reservedNicknameRepository()
            ->findByNickname(UserNickname::fromString('reserved'));

        self::assertInstanceOf(ReservedNickname::class, $restoredReservedNickname);
        self::assertTrue($restoredReservedNickname->isAssignedTo($user->id));
        self::assertTrue($this->reservedNicknameRepository()->isReserved(UserNickname::fromString('reserved')));
    }

    public function testDuplicateEmailFails(): void
    {
        $this->entityManager()->persist($this->createUser());
        $this->entityManager()->persist($this->createUser(email: 'TEST@example.com', nickname: 'other.nick'));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateNicknameFails(): void
    {
        $this->entityManager()->persist($this->createUser());
        $this->entityManager()->persist($this->createUser(email: 'other@example.com', nickname: 'YOGA.TEST'));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateReservedNicknameFails(): void
    {
        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('reserved')));
        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('RESERVED')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function createUser(
        string $email = 'test@example.com',
        string $nickname = 'yoga.test',
    ): User {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    private function createMedia(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    private function userBanRepository(): UserBanRepository
    {
        return $this->getContainer()->get(UserBanRepository::class);
    }

    private function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,320p' tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\TestCase;

abstract class MediaApplicationTestCase extends TestCase
{
    protected function createMedia(
        UserId|null $userId = null,
        MediaVisibility $visibility = MediaVisibility::Private,
        MediaType $type = MediaType::Image,
        MediaFileSize|null $size = null,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: $type,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $extension),
            mimeType: MediaMimeType::fromString($mimeType),
            size: $size ?? MediaFileSize::fromInt(1024),
            uploadedById: $userId ?? UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->run();
    }

    protected function conversionSpec(
        MediaImageConversionType $type = MediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaConversionSpec {
        return new MediaConversionSpec(type: $type, width: $width, height: $height);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

    protected function multipartUploadRepository(): MediaMultipartUploadRepository
    {
        return $this->getContainer()->get(MediaMultipartUploadRepository::class);
    }

    protected function imageConversionRepository(): MediaImageConversionRepository
    {
        return $this->getContainer()->get(MediaImageConversionRepository::class);
    }

    protected function videoConversionRepository(): MediaVideoConversionRepository
    {
        return $this->getContainer()->get(MediaVideoConversionRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/TestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests;

use Cycle\Database\DatabaseInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\Container;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Testing\TestCase as BaseTestCase;
use Spiral\Translator\TranslatorInterface;
use Tests\App\TestKernel;

class TestCase extends BaseTestCase
{
    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    {
        return TestKernel::create(
            directories: $this->defineDirectories(
                $this->rootDirectory(),
            ),
            container: $container,
        );
    }

    public function rootDirectory(): string
    {
        return __DIR__ . '/..';
    }

    public function defineDirectories(string $root): array
    {
        return TestRuntime::directories($root);
    }

    protected function setUp(): void
    {
        $this->beforeBooting(static function (ConfiguratorInterface $config): void {
            if (!$config->exists('session')) {
                return;
            }

            $config->modify('session', new Set('handler', null));
        });

        parent::setUp();

        $container = $this->getContainer();

        if ($container->has(TranslatorInterface::class)) {
            $container->get(TranslatorInterface::class)->setLocale('en');
        }
    }

    protected function tearDown(): void
    {
        try {
            \restore_error_handler();
            \restore_exception_handler();
            $this->disconnectDatabase();
        } finally {
            parent::tearDown();
        }

        // Раскомментируйте строку ниже, если нужно очищать runtime-директорию после тестов.
        // $this->cleanUpRuntimeDirectory();
    }

    private function disconnectDatabase(): void
    {
        $container = $this->getContainer();

        if (!$container->has(DatabaseInterface::class)) {
            return;
        }

        $database = $container->get(DatabaseInterface::class);
        $database->getDriver(DatabaseInterface::WRITE)->disconnect();
        $database->getDriver(DatabaseInterface::READ)->disconnect();
    }
}

exec
/bin/zsh -lc "rg -n \"function persist|function run|persist\\(|->run\\(\" tests app/src | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/TestRuntime.php:19:    public static function runtimeDirectory(string $root): string
tests/Kernel/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php:62:        $this->entityManager()->run();
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:38:        $this->persist($media);
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:74:        $this->persist($media);
tests/warmup.php:37:if ($kernel->run() === null) {
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:96:        $this->persist($media);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:157:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:22:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:47:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:61:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:37:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:60:        $this->persist($media, $multipartUpload);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:91:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:108:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:125:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:155:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:171:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:190:        $this->persist($media);
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php:82:        $this->entityManager()->persist($expiredAccess);
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php:83:        $this->entityManager()->persist($liveRefresh);
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php:84:        $this->entityManager()->run();
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:29:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:50:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:68:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:105:        $this->persist($media, $conversion);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:144:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:158:        $this->persist($media);
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:66:    protected function persistUser(string $email, string $nickname, bool $banned = false): User
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:80:        $this->entityManager()->persist($user);
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:81:        $this->entityManager()->run();
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:86:    protected function persistLoginCode(
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:106:        $this->entityManager()->persist($loginCode);
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:107:        $this->entityManager()->run();
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:112:    protected function persistRegistrationTicket(
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:126:        $this->entityManager()->persist($ticket);
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:127:        $this->entityManager()->run();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:30:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:59:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:82:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:106:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:134:        $this->persist($media);
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:49:    protected function persist(object ...$entities): void
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:52:            $this->entityManager()->persist($entity);
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:55:        $this->entityManager()->run();
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:23:        $this->persist($media);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:46:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:39:        $this->persist($media, $this->multipartUploadFor($media));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:58:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:128:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:155:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:186:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:188:        $this->persist(MediaImageConversion::create(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:215:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:217:        $this->persist(MediaVideoConversion::create(
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:19:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:29:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:46:        $this->persist($media);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:52:            $database->delete($table)->run();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:470:        $this->entityManager()->persist($user);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:471:        $this->entityManager()->run();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:489:        $this->entityManager()->persist($loginCode);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:490:        $this->entityManager()->run();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:504:        $this->entityManager()->persist($ticket);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:505:        $this->entityManager()->run();
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:57:        $this->entityManager->persist($user);
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:58:        $this->entityManager->run();
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:48:        $this->persist($first, $second);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:67:        $this->persist($loginCode);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:87:        $this->persist($loginCode);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:106:        $this->persist($ticket);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:120:        $this->persist($ticket);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:155:        $this->persist($accessToken, $refreshToken);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:190:        $this->persist($knownDeviceToken, $unknownDeviceToken);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:248:        $this->persist($ownTokenA, $ownTokenB, $foreignToken, $expiredToken);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:283:        $this->persist($accessToken, $refreshToken);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:303:        $this->entityManager()->persist(
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:313:        $this->entityManager()->persist(
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:326:        $this->entityManager()->run();
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:371:    private function persist(object ...$entities): void
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:374:            $this->entityManager()->persist($entity);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:377:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:49:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:50:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:70:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:71:        $this->entityManager()->persist($imageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:72:        $this->entityManager()->persist($videoConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:73:        $this->entityManager()->persist($multipartUpload);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:74:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:96:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:97:        $this->entityManager()->persist($imageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:98:        $this->entityManager()->persist($videoConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:99:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:122:        $this->entityManager()->persist($restoredMedia);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:123:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:139:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:140:        $this->entityManager()->persist($imageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:141:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:150:        $this->entityManager()->persist($restoredMedia);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:151:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:170:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:171:        $this->entityManager()->persist($imageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:172:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:182:        $this->entityManager()->persist($restoredImageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:183:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:198:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:199:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:210:        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:211:        $this->entityManager()->persist($this->createMedia(storageKey: $storageKey));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:215:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:225:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:226:        $this->entityManager()->persist($imageConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:227:        $this->entityManager()->persist($videoConversion);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:228:        $this->entityManager()->persist($multipartUpload);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:229:        $this->entityManager()->run();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:232:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:36:        $this->entityManager()->persist($role);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:37:        $this->entityManager()->persist($permission);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:38:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:70:        $this->entityManager()->persist($user);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:71:        $this->entityManager()->persist($role);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:72:        $this->entityManager()->persist($permission);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:73:        $this->entityManager()->persist($rolePermission);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:74:        $this->entityManager()->persist($userRole);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:75:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:91:        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:92:        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:96:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:101:        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:102:        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:106:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:114:        $this->entityManager()->persist($role);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:115:        $this->entityManager()->persist($permission);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:116:        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:117:        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:121:        $this->entityManager()->run();
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:129:        $this->entityManager()->persist($user);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:130:        $this->entityManager()->persist($role);
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:131:        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:132:        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php:136:        $this->entityManager()->run();
app/src/Modules/Media/README.md:94:  `persist(media + conversions); run()` с `markReadyMovedTo`. Операции идемпотентны
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:40:        $this->getContainer()->get(EntityManagerInterface::class)->run();
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:123:    public function runOnce(OutboxRelayBatchSize $outboxRelayBatchSize): int
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:131:    public function runLoop(OutboxRelayBatchSize $outboxRelayBatchSize, OutboxRelaySleepSeconds $outboxRelaySleepSeconds): never
tests/Feature/Modules/Notifications/Presentation/DispatchNotificationJobTest.php:122:        $this->getContainer()->get(EntityManagerInterface::class)->run();
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:154:    private function persistToken(UserId $userId): void
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:157:        $entityManager->persist(NotificationDeviceToken::create(
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:162:        $entityManager->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:54:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:89:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:126:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:156:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:193:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:223:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:252:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:262:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:263:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:302:            ->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:319:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:355:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:361:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:362:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:387:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:392:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:393:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:214:        $this->entityManager()->persist($read);
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:215:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:292:    private function persistNotification(UserId $userId): Notification
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:304:        $this->entityManager()->persist($notification);
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:305:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:282:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:283:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php:167:    private function persistToken(UserId $userId, string $token): void
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php:170:        $entityManager->persist(NotificationDeviceToken::create(
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php:175:        $entityManager->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:108:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:116:    private function persistOutboxEvent(string $type, string $payload): OutboxEventId
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:125:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:126:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php:224:    private function persistSetting(UserId $userId, NotificationChannel $channel, NotificationSettingStatus $status): void
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php:232:        $this->entityManager()->persist($setting);
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php:233:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:40:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:62:        $this->entityManager()->run();
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:85:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:347:    private function persistNotification(
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:362:        $this->persist($notification);
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:367:    private function persistToken(UserId $userId, string $token): void
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:369:        $this->persist(NotificationDeviceToken::create(
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:376:    private function persist(object $entity): void
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:379:        $entityManager->persist($entity);
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:380:        $entityManager->run();
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:103:        $this->entityManager->run();
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:161:        $this->entityManager->run();
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:180:        $this->entityManager->run();
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:208:        $this->entityManager->persist($authToken);
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:209:        $this->entityManager->run();
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:39:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:61:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:75:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:79:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:92:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:105:        $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:118:            $this->persist($notification);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:123:        $this->persist($this->createNotification(userId: UserId::generate()));
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:146:        $this->persist($firstUnread);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:147:        $this->persist($secondUnread);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:148:        $this->persist($read);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:158:        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:159:        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:163:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:183:        $this->persist($enabled);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:184:        $this->persist($disabled);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:208:        $this->entityManager()->persist(NotificationSetting::create(
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:214:        $this->entityManager()->persist(NotificationSetting::create(
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:223:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:234:        $this->persist($deviceToken);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:245:        $this->persist($restored);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:261:        $this->entityManager()->persist(NotificationDeviceToken::create(
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:266:        $this->entityManager()->persist(NotificationDeviceToken::create(
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:274:        $this->entityManager()->run();
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:318:    private function persist(object $entity): void
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:320:        $this->entityManager()->persist($entity);
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php:321:        $this->entityManager()->run();
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:68:        $this->entityManager->persist($media);
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:69:        $this->entityManager->run();
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:86:        $this->entityManager->persist($multipartUpload);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:60:        $this->entityManager->run();
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:82:        $this->entityManager->persist($media);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:108:        $this->entityManager->persist($media);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:109:        $this->entityManager->persist(MediaMultipartUpload::create(
tests/Feature/Modules/System/Console/OpenApiPublishAssetsCommandTest.php:138:    private function runCommandSwallowingWarnings(string $command): string
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkFinalThenThrowQueueStatusCore.php:31:        $this->entityManager->persist($this->storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkFinalThenThrowQueueStatusCore.php:32:        $this->entityManager->run();
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php:21:    private function persistQueuedEvent(string $payload = '{"text":"debug","createdAt":"2026-05-25T16:05:00+00:00"}'): StoredOutboxEvent
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php:33:        $this->entityManager()->persist($storedOutboxEvent);
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php:34:        $this->entityManager()->run();
app/src/Modules/Auth/Application/Command/RequestLoginCode/RequestLoginCodeHandler.php:57:            $this->entityManager->persist($activeCode);
app/src/Modules/Auth/Application/Command/RequestLoginCode/RequestLoginCodeHandler.php:73:        $this->entityManager->persist($loginCode);
app/src/Modules/Auth/Application/Command/RequestLoginCode/RequestLoginCodeHandler.php:79:        $this->entityManager->run();

exec
/bin/zsh -lc "sed -n '1,260p' app/config/cycle.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);


/**
 * Конфигурация Cycle ORM.
 *
 * @link https://spiral.dev/docs/basics-orm#orm
 */
return [
    'schema' => [
        /**
         * true - схема будет сохранена в кэше после компиляции.
         * После изменения entity обновите схему командой `php app.php cycle`.
         *
         * false - схема не будет кэшироваться и будет обновляться автоматически в режиме разработки.
         */
        'cache' => \env('CYCLE_SCHEMA_CACHE', true),

        /**
         * Настройки по умолчанию для сегментов схемы, которые не были заданы явно.
         */
        'defaults' => [
            \Cycle\ORM\SchemaInterface::MAPPER => \App\Shared\Infrastructure\Cycle\LazyGhostMapper::class,
            // SchemaInterface::REPOSITORY => \Cycle\ORM\Select\Repository::class,
            // SchemaInterface::SCOPE => null,
            // SchemaInterface::TYPECAST_HANDLER => [
            //    \Cycle\ORM\Parser\Typecast::class,
            // ],
        ],

        'collections' => [
            'default' => 'illuminate',
            'factories' => ['illuminate' => new Cycle\ORM\Collection\IlluminateCollectionFactory()],
        ],

        /**
         * Генераторы схемы.
         * null - использовать генераторы, заданные bootloader-ами.
         */
        'generators' => null,

        // 'generators' => [
        //        \Cycle\Annotated\Embeddings::class,
        //        \Cycle\Annotated\Entities::class,
        //        \Cycle\Annotated\MergeColumns::class,
        //        \Cycle\Schema\Generator\ResetTables::class,
        //        \Cycle\Schema\Generator\GenerateRelations::class,
        //        \Cycle\Schema\Generator\ValidateEntities::class,
        //        \Cycle\Schema\Generator\RenderTables::class,
        //        \Cycle\Schema\Generator\RenderRelations::class,
        //        \Cycle\Annotated\TableInheritance::class,
        //        \Cycle\Annotated\MergeIndexes::class
        //        \Cycle\Schema\Generator\GenerateTypecast::class,
        // ],
    ],

    'warmup' => \env('RR_MODE') === null ? false : \env('CYCLE_SCHEMA_WARMUP', false),

    /**
     * Пользовательские типы связей для entity.
     */
    'customRelations' => [
        // \Cycle\ORM\Relation::EMBEDDED => [
        //     \Cycle\ORM\Config\RelationConfig::LOADER => \Cycle\ORM\Select\Loader\EmbeddedLoader::class,
        //     \Cycle\ORM\Config\RelationConfig::RELATION => \Cycle\ORM\Relation\Embedded::class,
        // ]
    ],
];

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Shared/Domain/Trait/HasTimestamps.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

use Cycle\Annotated\Annotation\Column;

trait HasTimestamps
{
    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    public private(set) \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    public private(set) \DateTimeImmutable $updatedAt;

    public function initializeTimestamps(\DateTimeImmutable|null $now = null): void
    {
        $now ??= new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(\DateTimeImmutable|null $now = null): void
    {
        $this->updatedAt = $now ?? new \DateTimeImmutable();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Infrastructure/Cycle/WhenSelect.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use Cycle\ORM\Select;

/**
 * Select с условным построением запроса: метод when() выполняет замыкание только при
 * истинном условии, поэтому условные where пишутся внутри fluent-цепочки без разрыва на if.
 *
 * @template-covariant TEntity of object
 *
 * @extends Select<TEntity>
 */
class WhenSelect extends Select
{
    /**
     * Выполнить замыкание над запросом, если условие истинно.
     *
     * @param callable(self<TEntity>): void $callback
     *
     * @return $this
     */
    public function when(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Access/Repository/RoleRepository.php && sed -n '1,220p' app/src/Modules/Access/Repository/RolePermissionRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<Role>
 */
final class RoleRepository extends Repository
{
    public function findById(RoleId $roleId): Role|null
    {
        return $this->findByPK($roleId->value());
    }

    public function findBySlug(RoleSlug $slug): Role|null
    {
        return $this->findOne(['slug' => $slug->value()]);
    }

    public function findByIds(RoleId ...$ids): RoleCollection
    {
        return new RoleCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(RoleId $roleId): string => $roleId->value(),
                    $ids,
                )))
                ->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): RoleCollection
    {
        return new RoleCollection($this->select()->where($scope)->orderBy($orderBy)->fetchAll());
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<RolePermission>
 */
final class RolePermissionRepository extends Repository
{
    public function findByRoleId(RoleId $roleId): RolePermissionCollection
    {
        return new RolePermissionCollection(
            $this->select()
                ->where('role_id', $roleId->value())
                ->fetchAll(),
        );
    }

    public function exists(RoleId $roleId, PermissionId $permissionId): bool
    {
        return $this->findOne([
            'role_id' => $roleId->value(),
            'permission_id' => $permissionId->value(),
        ]) !== null;
    }
}

exec
/bin/zsh -lc "rg -n \"partial|where\\('id'|orderBy\\('id'|limit\\(\" app/src/Modules tests/Feature | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/Notifications/Repository/NotificationRepository.php:44:                        $query->where('id', '<', $cursorId);
app/src/Modules/Notifications/Repository/NotificationRepository.php:48:                ->limit($limit)
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:93:            ->where('id', $outboxEventId->value())
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:94:            ->limit(1)
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:109:            ->where('id', $outboxEventId->value())
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:110:            ->limit(1)
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:125:            ->where('id', $outboxEventId->value())
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php:126:            ->limit(1)
app/src/Modules/Access/Repository/PermissionRepository.php:33:                ->where('id', 'in', new Parameter(\array_map(
app/src/Modules/Access/Repository/RoleRepository.php:33:                ->where('id', 'in', new Parameter(\array_map(
app/src/Modules/Outbox/Repository/OutboxEventRepository.php:44:                ->limit($outboxRelayBatchSize->value())

exec
/bin/zsh -lc "rg -n \"class .*Repository extends AbstractRepository|when\\(\" app/src/Modules tests/Feature | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/Outbox/Repository/OutboxEventRepository.php:18:final class OutboxEventRepository extends AbstractRepository
app/src/Modules/Notifications/Repository/NotificationRepository.php:18:final class NotificationRepository extends AbstractRepository
app/src/Modules/Notifications/Repository/NotificationRepository.php:41:                ->when(
app/src/Modules/Notifications/Repository/NotificationSettingRepository.php:17:final class NotificationSettingRepository extends AbstractRepository
app/src/Modules/Notifications/Repository/NotificationDeviceTokenRepository.php:16:final class NotificationDeviceTokenRepository extends AbstractRepository
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php:15:final class MediaVideoConversionRepository extends AbstractRepository
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php:14:final class MediaMultipartUploadRepository extends AbstractRepository
app/src/Modules/Media/Repository/MediaImageConversionRepository.php:15:final class MediaImageConversionRepository extends AbstractRepository
app/src/Modules/Media/Repository/MediaRepository.php:16:final class MediaRepository extends AbstractRepository

codex
Репозитории с постраничным чтением уже есть в `Notifications`, поэтому планируемые методы по `id DESC` реализуемы. Но в плане для `PostRepository` упомянут “опциональный фильтр статуса”, а в сигнатуре параметра статуса нет — это конкретная недосказанность.
exec
/bin/zsh -lc "sed -n '1,240p' app/database/migrations/20260613.130000_0_create_notification_domain_tables.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateNotificationDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('notifications')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('outbox_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('title', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('body', 'text', ['nullable' => false])
            ->addColumn('action_type', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('action_id', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('actor', 'json', ['nullable' => true])
            ->addColumn('read_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['outbox_id'], ['unique' => true])
            ->addIndex(['user_id', 'id'])
            ->addIndex(['user_id', 'read_at'])
            ->create();

        $this->table('notification_settings')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('channel', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('enabled', 'boolean', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['user_id', 'type', 'channel'], ['unique' => true])
            ->create();

        $this->table('notification_device_tokens')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('token', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('platform', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['user_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notification_device_tokens')->drop();
        $this->table('notification_settings')->drop();
        $this->table('notifications')->drop();
    }
}

exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Notifications/Repository/NotificationRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Cycle\WhenSelect;

/**
 * @extends AbstractRepository<Notification>
 */
final class NotificationRepository extends AbstractRepository
{
    public function findByOutboxId(NotificationOutboxId $outboxId): Notification|null
    {
        return $this->findOne(['outbox_id' => $outboxId->value()]);
    }

    public function findByIdForRecipient(NotificationId $id, UserId $userId): Notification|null
    {
        return $this->findOne(['id' => $id->value(), 'user_id' => $userId->value()]);
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md:32): id DESC, при наличии курсора берём строки
     * строго старше курсора. Вызывающий Query запрашивает limit+1 для вычисления nextCursor.
     */
    public function findPageForRecipient(UserId $userId, NotificationId|null $cursor, int $limit): NotificationCollection
    {
        $cursorId = $cursor?->value();

        return new NotificationCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }

    public function countUnreadForRecipient(UserId $userId): int
    {
        return $this->select()
            ->where('user_id', $userId->value())
            ->where('read_at', '=', null)
            ->count();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Notifications/Domain/Entity/Notification.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActionIdTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActionTypeTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActorTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationReadStateTypecast;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Строка инбокса (канал database) — создаётся фоновой рассылкой. Deep-link хранится в двух
 * nullable-колонках action_type/action_id и собирается доменным методом action().
 */
#[Entity(
    role: 'notification',
    table: 'notifications',
    repository: NotificationRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Notification
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: NotificationId::class)]
    public private(set) NotificationId $id;

    #[Column(type: 'uuid', name: 'outbox_id', typecast: NotificationOutboxId::class)]
    public private(set) NotificationOutboxId $outboxId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'string(255)', typecast: NotificationTypeCode::class)]
    public private(set) NotificationTypeCode $type;

    #[Column(type: 'string(255)', typecast: NotificationTitle::class)]
    public private(set) NotificationTitle $title;

    #[Column(type: 'text', typecast: NotificationBody::class)]
    public private(set) NotificationBody $body;

    #[Column(type: 'string(255)', name: 'action_type', nullable: true, typecast: NotificationActionTypeTypecast::class)]
    public private(set) NotificationActionType $actionType;

    #[Column(type: 'string(255)', name: 'action_id', nullable: true, typecast: NotificationActionIdTypecast::class)]
    public private(set) NotificationActionId $actionId;

    #[Column(type: 'json', name: 'actor', nullable: true, typecast: NotificationActorTypecast::class)]
    public private(set) NotificationActor $actor;

    #[Column(type: 'datetime', name: 'read_at', nullable: true, typecast: NotificationReadStateTypecast::class)]
    public private(set) NotificationReadState $readState;

    public static function create(
        NotificationOutboxId $outboxId,
        UserId $userId,
        NotificationTypeCode $type,
        NotificationTitle $title,
        NotificationBody $body,
        NotificationAction $action,
        NotificationActor $actor,
        \DateTimeImmutable $triggeredAt,
    ): self {
        $notification = new self();
        $notification->id = NotificationId::generate();
        $notification->outboxId = $outboxId;
        $notification->userId = $userId;
        $notification->type = $type;
        $notification->title = $title;
        $notification->body = $body;
        $notification->actionType = $action->actionType();
        $notification->actionId = $action->actionId();
        $notification->actor = $actor;
        $notification->readState = NotificationReadState::unread();
        $notification->initializeTimestamps($triggeredAt);

        return $notification;
    }

    /**
     * Доменный метод (не property-hook): собирает переход из двух backing-колонок.
     */
    public function action(): NotificationAction
    {
        return NotificationAction::fromParts(actionType: $this->actionType, actionId: $this->actionId);
    }

    public function markRead(\DateTimeImmutable $readAt): void
    {
        if ($this->readState->isRead()) {
            return;
        }

        $this->readState = NotificationReadState::readAt($readAt);
        $this->touch($readAt);
    }

    public function isRead(): bool
    {
        return $this->readState->isRead();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Modules\Notifications\Repository\NotificationSettingRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class NotificationRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresNotificationWithLink(): void
    {
        $userId = UserId::generate();
        $triggeredAt = new \DateTimeImmutable('2026-06-13 10:00:00');
        $notification = $this->createNotification(
            userId: $userId,
            action: NotificationAction::linkTo('chat', '42'),
            triggeredAt: $triggeredAt,
        );

        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertTrue($notification->id->equals($restored->id));
        self::assertTrue($notification->outboxId->equals($restored->outboxId));
        self::assertSame('chat.message_received', $restored->type->value());
        self::assertSame('Новое сообщение', $restored->title->value());
        self::assertSame('Вам пришло сообщение', $restored->body->value());
        self::assertTrue($restored->action()->hasLink());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $restored->action()->jsonSerialize());
        self::assertFalse($restored->isRead());
        self::assertEquals($triggeredAt, $restored->createdAt);
    }

    public function testStoresNotificationWithoutLink(): void
    {
        $userId = UserId::generate();
        $notification = $this->createNotification(userId: $userId, action: NotificationAction::none());

        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertFalse($restored->action()->hasLink());
        self::assertNull($restored->action()->jsonSerialize());
    }

    public function testMarkReadIsPersisted(): void
    {
        $userId = UserId::generate();
        $notification = $this->createNotification(userId: $userId);
        $this->persist($notification);

        $readAt = new \DateTimeImmutable('2026-06-13 11:00:00');
        $notification->markRead($readAt);
        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertTrue($restored->isRead());
        self::assertEquals($readAt, $restored->readState->markedAt());
    }

    public function testFindByOutboxIdFindsAndMisses(): void
    {
        $notification = $this->createNotification(userId: UserId::generate());
        $this->persist($notification);
        $this->cleanOrmHeap();

        self::assertInstanceOf(
            Notification::class,
            $this->notificationRepository()->findByOutboxId($notification->outboxId),
        );
        self::assertNull($this->notificationRepository()->findByOutboxId(NotificationOutboxId::generate()));
    }

    public function testFindByIdForRecipientRejectsForeignOwner(): void
    {
        $notification = $this->createNotification(userId: UserId::generate());
        $this->persist($notification);
        $this->cleanOrmHeap();

        self::assertNull($this->notificationRepository()->findByIdForRecipient($notification->id, UserId::generate()));
    }

    public function testFindPageForRecipientOrdersByIdDescAndPaginates(): void
    {
        $userId = UserId::generate();
        $created = [];

        for ($index = 0; $index < 3; $index++) {
            $notification = $this->createNotification(userId: $userId);
            $this->persist($notification);
            $created[] = $notification;
        }

        // Чужое уведомление не попадает в страницу получателя.
        $this->persist($this->createNotification(userId: UserId::generate()));
        $this->cleanOrmHeap();

        $expectedIdsDesc = $this->idsDesc($created);

        $firstPage = $this->notificationRepository()->findPageForRecipient($userId, null, 2);
        self::assertSame(\array_slice($expectedIdsDesc, 0, 2), $this->ids($firstPage->all()));

        $lastOnFirstPage = $firstPage->last();
        self::assertInstanceOf(Notification::class, $lastOnFirstPage);
        $secondPage = $this->notificationRepository()->findPageForRecipient($userId, $lastOnFirstPage->id, 2);

        self::assertSame(\array_slice($expectedIdsDesc, 2), $this->ids($secondPage->all()));
    }

    public function testCountsUnreadOnly(): void
    {
        $userId = UserId::generate();
        $firstUnread = $this->createNotification(userId: $userId);
        $secondUnread = $this->createNotification(userId: $userId);
        $read = $this->createNotification(userId: $userId);
        $read->markRead(new \DateTimeImmutable('2026-06-13 11:00:00'));

        $this->persist($firstUnread);
        $this->persist($secondUnread);
        $this->persist($read);
        $this->cleanOrmHeap();

        self::assertSame(2, $this->notificationRepository()->countUnreadForRecipient($userId));
    }

    public function testOutboxIdIsUnique(): void
    {
        $outboxId = NotificationOutboxId::generate();

        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));
        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testSettingRoundTripAndLookups(): void
    {
        $userId = UserId::generate();
        $type = NotificationTypeCode::fromString('chat.message_received');
        $enabled = NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        );
        $disabled = NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Realtime,
            status: NotificationSettingStatus::Disabled,
        );

        $this->persist($enabled);
        $this->persist($disabled);
        $this->cleanOrmHeap();

        $forType = $this->settingRepository()->findForUserAndType($userId, $type);
        self::assertCount(2, $forType);
        self::assertCount(2, $this->settingRepository()->findForUser($userId));

        $restoredEnabled = $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Push);
        $restoredDisabled = $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Realtime);

        self::assertInstanceOf(NotificationSetting::class, $restoredEnabled);
        self::assertInstanceOf(NotificationSetting::class, $restoredDisabled);
        self::assertTrue($restoredEnabled->isEnabled());
        self::assertFalse($restoredDisabled->isEnabled());
        self::assertNull(
            $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Database),
        );
    }

    public function testSettingUserTypeChannelIsUnique(): void
    {
        $userId = UserId::generate();
        $type = NotificationTypeCode::fromString('chat.message_received');

        $this->entityManager()->persist(NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        ));
        $this->entityManager()->persist(NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Disabled,
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDeviceTokenRoundTripReassignAndLookups(): void
    {
        $owner = UserId::generate();
        $deviceToken = NotificationDeviceToken::create(
            userId: $owner,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        );
        $this->persist($deviceToken);
        $this->cleanOrmHeap();

        $restored = $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token'));
        self::assertInstanceOf(NotificationDeviceToken::class, $restored);
        self::assertTrue($owner->equals($restored->userId));
        self::assertSame(DevicePlatform::Ios, $restored->platform);
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($owner));

        $newOwner = UserId::generate();
        $restored->reassignTo($newOwner, DevicePlatform::Android);
        $this->persist($restored);
        $this->cleanOrmHeap();

        $reassigned = $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token'));
        self::assertInstanceOf(NotificationDeviceToken::class, $reassigned);
        self::assertTrue($newOwner->equals($reassigned->userId));
        self::assertSame(DevicePlatform::Android, $reassigned->platform);
        self::assertCount(0, $this->deviceTokenRepository()->findAllForUser($owner));
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($newOwner));
        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('missing-token')));
    }

    public function testTokenIsUnique(): void
    {
        $token = DeviceToken::fromString('fcm-token');


exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Notifications/Domain/ValueObject/NotificationPayload.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 exited 1 in 0ms:
sed: app/src/Modules/Notifications/Domain/ValueObject/NotificationPayload.php: No such file or directory

codex
Блокеров уровня «план невозможно реализовать» не нашёл. Но есть важные риски перед исполнением:

- [важное] `post_likes.user_id` и `comment_likes.user_id` стоят на `CASCADE`, но лайки имеют денормализованные счётчики в `posts` и `comments`; жёсткое удаление пользователя удалит строки лайков без пересчёта счётчиков — заменить на `RESTRICT` или явно зафиксировать, что hard-delete пользователей невозможен и такие каскады не используются
- [важное] в репозиториях курсор задан как `?string`, хотя в проекте уже есть образец `NotificationRepository::findPageForRecipient(..., NotificationId|null $cursor, int $limit)`; сырая строка обходит проверку UUID v7 — заменить на `PostId|null` и `CommentId|null`
- [важное] `TagRepository::findByTexts(list)` не является нормальным контрактом проекта: тип не определён, массивы в контрактах ограничены правилами — сделать `findByTexts(TagText ...$tagTexts)` или отдельную `TagTextCollection`
- [важное] план говорит про «опциональный фильтр статуса через when()» в `PostRepository::findByUserId`, но в сигнатуре фильтра нет — добавить явный `PostStatus|null $status` или убрать обещание
- [важное] feature-тесты должны создавать реальные строки `users` и `media` для всех FK; в сценариях это не прописано, а простые `UserId::generate()` / `PostMediaReference::pointingTo()` будут падать на внешних ключах — добавить общий fixture-helper для пользователя и медиа
- [важное] у FK с `indexCreate => false` не хватает явных индексов на часть ссылок: `post_media.media_id`, `tags.created_by_id`, `post_blocks.blocked_by_id`, `post_blocks.unblocked_by_id`, возможно `comments.deleted_by_id`; это не ломает миграцию, но даст дорогие проверки `RESTRICT/SET NULL` — добавить индексы или осознанно обосновать отказ
- [важное] методы `Comment::delete(...)` и `PostBlock::markUnblocked(...)` принимают null-object VO, поэтому можно передать «пустые» значения и получить противоречивую тройку полей — в доменных методах проверить, что дата и пользователь заполнены, или ввести отдельные VO/фабрики для действия
- [важное] `PostText` описан правильно как `none()` для отсутствия и `fromString()` для непустого текста, но тест «пустой `PostText` → `none()`» двусмысленен — уточнить, что `fromString('')` бросает исключение, а `none()` создаётся отдельной фабрикой
- [важное] плоские join-сущности в плане получают `HasTimestamps`, хотя эталон `RolePermission`/`UserRole` timestamps не имеет; это реализуемо, но не «по образцу» — либо оставить timestamps как осознанное отличие, либо убрать их из простых join-таблиц
- [мелочь] проверку `CASCADE/SET NULL` лучше всегда делать после `cleanOrmHeap()` и повторного чтения, не только для `SET NULL`; иначе легко проверить объект из памяти, а не фактическое состояние базы — явно добавить это в критерии тестов
- [мелочь] `PostRepository::findByUserId` по названию не ясно, возвращает ли черновики, заблокированные и удалённые записи — либо переименовать в более общее имя, либо добавить фильтры по статусу и удалению
- [мелочь] проверка self-FK через `->update()` правильно вынесена в миграционную фазу, но в проекте нет такого прецедента именно для FK — оставить отдельную раннюю проверку наката/отката как обязательную, не как «практически проверяется feature-тестами»

Оценка готовности: **84/100**.

Ключевые риски:

1. Денормализованные счётчики могут разъехаться из-за каскадного удаления лайков.
2. Контракты репозиториев местами слабее правил проекта: сырой cursor и неоформленный список тегов.
3. Миграция с self-FK и большим числом связей реализуема, но требует ранней проверки наката/отката до написания остального слоя.
tokens used
142 481
Блокеров уровня «план невозможно реализовать» не нашёл. Но есть важные риски перед исполнением:

- [важное] `post_likes.user_id` и `comment_likes.user_id` стоят на `CASCADE`, но лайки имеют денормализованные счётчики в `posts` и `comments`; жёсткое удаление пользователя удалит строки лайков без пересчёта счётчиков — заменить на `RESTRICT` или явно зафиксировать, что hard-delete пользователей невозможен и такие каскады не используются
- [важное] в репозиториях курсор задан как `?string`, хотя в проекте уже есть образец `NotificationRepository::findPageForRecipient(..., NotificationId|null $cursor, int $limit)`; сырая строка обходит проверку UUID v7 — заменить на `PostId|null` и `CommentId|null`
- [важное] `TagRepository::findByTexts(list)` не является нормальным контрактом проекта: тип не определён, массивы в контрактах ограничены правилами — сделать `findByTexts(TagText ...$tagTexts)` или отдельную `TagTextCollection`
- [важное] план говорит про «опциональный фильтр статуса через when()» в `PostRepository::findByUserId`, но в сигнатуре фильтра нет — добавить явный `PostStatus|null $status` или убрать обещание
- [важное] feature-тесты должны создавать реальные строки `users` и `media` для всех FK; в сценариях это не прописано, а простые `UserId::generate()` / `PostMediaReference::pointingTo()` будут падать на внешних ключах — добавить общий fixture-helper для пользователя и медиа
- [важное] у FK с `indexCreate => false` не хватает явных индексов на часть ссылок: `post_media.media_id`, `tags.created_by_id`, `post_blocks.blocked_by_id`, `post_blocks.unblocked_by_id`, возможно `comments.deleted_by_id`; это не ломает миграцию, но даст дорогие проверки `RESTRICT/SET NULL` — добавить индексы или осознанно обосновать отказ
- [важное] методы `Comment::delete(...)` и `PostBlock::markUnblocked(...)` принимают null-object VO, поэтому можно передать «пустые» значения и получить противоречивую тройку полей — в доменных методах проверить, что дата и пользователь заполнены, или ввести отдельные VO/фабрики для действия
- [важное] `PostText` описан правильно как `none()` для отсутствия и `fromString()` для непустого текста, но тест «пустой `PostText` → `none()`» двусмысленен — уточнить, что `fromString('')` бросает исключение, а `none()` создаётся отдельной фабрикой
- [важное] плоские join-сущности в плане получают `HasTimestamps`, хотя эталон `RolePermission`/`UserRole` timestamps не имеет; это реализуемо, но не «по образцу» — либо оставить timestamps как осознанное отличие, либо убрать их из простых join-таблиц
- [мелочь] проверку `CASCADE/SET NULL` лучше всегда делать после `cleanOrmHeap()` и повторного чтения, не только для `SET NULL`; иначе легко проверить объект из памяти, а не фактическое состояние базы — явно добавить это в критерии тестов
- [мелочь] `PostRepository::findByUserId` по названию не ясно, возвращает ли черновики, заблокированные и удалённые записи — либо переименовать в более общее имя, либо добавить фильтры по статусу и удалению
- [мелочь] проверка self-FK через `->update()` правильно вынесена в миграционную фазу, но в проекте нет такого прецедента именно для FK — оставить отдельную раннюю проверку наката/отката как обязательную, не как «практически проверяется feature-тестами»

Оценка готовности: **84/100**.

Ключевые риски:

1. Денормализованные счётчики могут разъехаться из-за каскадного удаления лайков.
2. Контракты репозиториев местами слабее правил проекта: сырой cursor и неоформленный список тегов.
3. Миграция с self-FK и большим числом связей реализуема, но требует ранней проверки наката/отката до написания остального слоя.
