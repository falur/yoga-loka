---
title: Выделение тегов в отдельный модуль Tags
date: 2026-06-17 19:02
mode: strict
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

Выделить словарь тегов из только что созданного (ещё незакоммиченного) модуля `Posts`
в отдельный модуль `Tags`, потому что тег — общая сущность приложения (один словарь
хэштегов, в будущем используется не только постами). Связь «пост ↔ тег» (`PostTag`)
остаётся в `Posts`.

Готово, когда:
- словарь тегов (`Tag`, `TagText`, `TagCollection`, `TagRepository`) живёт в
  `App\Modules\Tags`, а общий идентификатор `TagId` — в `App\Shared\Domain\ValueObject`
  (как `UserId`);
- в `Posts` не осталось ни одного tag-классa, кроме связи `PostTag` и её типов;
- production-зависимостей `Posts\Domain → Tags\Domain` нет (Posts ссылается на тег
  только через `TagId` из `Shared`);
- тесты тегов лежат под `Tests\…\Modules\Tags`, тесты `PostTag` — под `Posts`;
- `make phpstan` и `make test` зелёные, покрытие 100%.

## Контекст

- Структура модуля по `arch.md`: `Domain / Application / Repository / Infrastructure /
  Presentation`. Текущий объём `Posts` — только DB-слой (`Domain`, `Repository`,
  `Infrastructure`), без `Application`/`Presentation`. Новый модуль `Tags` создаётся в
  том же объёме (DB-слой), Application откладывается (решение пользователя).
- Правило зависимостей `arch.md`: `Domain → PHP stdlib, свой Domain, Shared/Domain`.
  Поэтому общий идентификатор `TagId`, на который ссылается `PostTag` из `Posts`,
  обязан лежать в `Shared/Domain/ValueObject` — иначе `Posts\Domain` зависел бы от
  `Tags\Domain`. Прямой precedent: сущность `User` живёт в `Modules\User`, а её
  идентификатор `UserId` — в `App\Shared\Domain\ValueObject\UserId`, потому что на
  пользователя по id ссылаются многие модули. `TagId` повторяет ровно эту роль.
- `TagId` — `final readonly class TagId extends AbstractUuidV7Id {}` (пустой наследник
  без собственных строк кода). `UserId` устроен так же и не имеет отдельного теста:
  базовый класс покрыт `tests/Unit/Shared/Domain/ValueObject/AbstractUuidV7IdTest.php`,
  у пустого наследника нет исполняемых строк, поэтому отдельный тест не нужен и на
  100% покрытие не влияет.
- `PostTag` хранит `TagId` как VO-колонку (`tag_id`) и НЕ имеет Cycle-relation на
  `Tag` (`app/src/Modules/Posts/Domain/Entity/PostTag.php`). Поэтому разрыв чистый:
  ORM-связь между модулями рвать не нужно, меняется только импорт `TagId`.
- `TagText` в production используется только `TagRepository` (`findByText`,
  `findByTexts`); `Posts` на `TagText` не ссылается. Значит `TagText` принадлежит
  модулю `Tags`, в `Shared` его выносить не нужно.
- У тега нет отдельного typecast-класса: `TagId`/`TagText` кастятся общим
  `App\Shared\Infrastructure\Cycle\ValueObjectCast` по соглашению
  (`fromString`/`value()`), `Tag` объявляет `typecast: [Typecast::class,
  ValueObjectCast::class]`. Переносить из `Infrastructure/Cycle` нечего.
- `TagRepository` наследует `App\Shared\Infrastructure\Cycle\AbstractRepository` —
  общий, к модулю не привязан. Отдельного bootloader-а или DI-bind для тега нет: Cycle
  резолвит репозиторий по атрибуту `repository: TagRepository::class` на `#[Entity]`
  сущности `Tag`, поэтому `$container->get(TagRepository::class)` в тестах продолжит
  работать после смены FQCN — нужно лишь, чтобы атрибут указывал на новый
  `App\Modules\Tags\Repository\TagRepository`.
- Сущности Cycle авто-сканируются tokenizer-ом по `app/src` (в `app/config` нет
  whitelist директорий/namespace для сущностей — модуль `Posts` нашёлся сам). Новый
  `app/src/Modules/Tags` будет просканирован автоматически, правка конфигов не нужна.
- Миграции по `arch.md` — единая глобальная линейная история, «дробить по модулям
  нельзя». Таблица `tags` создаётся в
  `app/database/migrations/20260617.160942_0_create_posts_domain_tables.php` ПЕРЕД
  `post_tags` (FK `post_tags.tag_id → tags.id` RESTRICT) и ПЕРЕД `posts`. Порядок уже
  верный. Структура схемы при переносе кода не меняется.
- Тег сейчас перемешан с постами в тестах: `JoinEntityTest`, `PostsIdentifierTest`,
  `PostsTextValueObjectTest`, `PostsCollectionTest` (unit), `PostsRepositoryTestCase`,
  `TagAndPostTagRepositoryTest`, `PostRepositoryTest` (feature).

## Принятые решения

- **`TagId` → `App\Shared\Domain\ValueObject\TagId`** (как `UserId`). Источник:
  `arch.md`/`rules.md` (правило зависимостей `Domain`, размещение общих
  идентификаторов в `Shared/Domain/ValueObject`) + precedent `UserId`. Альтернатива
  «`TagId` в модуле `Tags`» отклонена: она создаёт запрещённую зависимость
  `Posts\Domain → Tags\Domain`.
- **Словарь тегов → `App\Modules\Tags`**: `Tag` (Entity), `TagText` (VO),
  `TagCollection`, `TagRepository`. Источник: запрос пользователя (теги общие,
  отдельный модуль).
- **`PostTag` и его типы остаются в `Posts`** (`PostTag`, `PostTagId`,
  `PostTagCollection`, `PostTagRepository`): это связь, принадлежит постам.
- **Application-слой `Tags` откладывается** (решение пользователя): модуль создаётся
  как DB-слой, без `Application`/`Presentation`. `FindOrCreateTag` и связывание с
  Posts — отдельная задача под будущий use-case создания поста.
- **Миграция не дробится и не переименовывается**: `tags` остаётся в существующей
  миграции, файл и класс `CreatePostsDomainTables` не трогаем. Источник: `arch.md`
  (единая кросс-модульная история миграций). Решение `decision_mode: recommend_and_ask`
  — рекомендация подтверждена архитектурой, структура схемы не меняется.
- **`TagId` без отдельного теста** (пустой наследник `AbstractUuidV7Id`, как `UserId`).
- **Объём `normal`, тесты `after_each_phase`**: 2 фазы, каждая оставляет `make test`
  и `make phpstan` зелёными. Фаза 1 — перенос production и приведение тестов к новым
  путям импорта (suite зелёный в старой раскладке файлов). Фаза 2 — приведение тестов
  к границам модулей (вынос tag-тестов в `Modules\Tags`, очистка Posts-тестов).

## Целевой алгоритм

Поведение системы в рантайме не меняется: это перенос кода без изменения схемы БД и
без новых сценариев. После переноса:

- создание тега: `Tag::create(TagText, UserId)` (модуль `Tags`) → `Tag` с
  `TagId` (Shared), `TagText` (Tags), `createdById: UserId` (Shared); запись в таблицу
  `tags` через `EntityManager` (в будущем Application-сценарии, вне объёма);
- чтение тегов: `TagRepository` (модуль `Tags`) — `findById(TagId)`,
  `findByText(TagText)`, `findByTexts(TagText ...)`;
- связь с постом: `PostTag::create(PostId, TagId)` (модуль `Posts`) хранит `TagId`
  (Shared) в колонке `tag_id`; чтение через `PostTagRepository` (модуль `Posts`).

## Контракты реализации

### Данные и БД

Структура схемы не меняется. Таблицы `tags` и `post_tags` остаются как есть, FK
`post_tags.tag_id → tags.id` (RESTRICT) и `tags.created_by_id → users.id` (RESTRICT)
сохраняются. Миграция `20260617.160942_0_create_posts_domain_tables.php` не
редактируется (порядок `tags` → `posts` → `post_tags` корректен). Backfill/rollback не
требуются — таблицы ещё не в общей истории (изменения незакоммичены), данных нет.

Маппинг Entity → таблица сохраняется идентично: `Tag` (role `tag`, table `tags`,
колонки `id`/`text`/`created_by_id`/`created_at`/`updated_at`), `PostTag` (role
`post_tag`, table `post_tags`). Меняются только PHP-namespace и импорт `TagId`.

### API и внешние контракты

Не затрагивается. Маршрутов, очередей, событий и внешних интеграций задача не касается
(чистый DB-слой, Presentation/Application отсутствуют).

## Фазы выполнения

### 1. Перенос словаря тегов в `Tags` и `TagId` в `Shared`

Цель: физически переместить классы тегов в новые namespace и сделать так, чтобы весь
код и все тесты ссылались на новые расположения, не меняя пока раскладку тестовых
файлов. По завершении приложение и тесты работают из новых мест.

Что сделать:
- Создать общий идентификатор `app/src/Shared/Domain/ValueObject/TagId.php`
  (`namespace App\Shared\Domain\ValueObject;`, `final readonly class TagId extends
  AbstractUuidV7Id {}`) — содержимое идентично текущему, меняется namespace.
- Создать модуль `Tags` (DB-слой), перенеся файлы 1:1 со сменой namespace и импортов:
  - `app/src/Modules/Tags/Domain/ValueObject/TagText.php` (`namespace
    App\Modules\Tags\Domain\ValueObject;`, тело без изменений).
  - `app/src/Modules/Tags/Domain/Collection/TagCollection.php` (`namespace
    App\Modules\Tags\Domain\Collection;`, импорт `Tag` из
    `App\Modules\Tags\Domain\Entity\Tag`).
  - `app/src/Modules/Tags/Repository/TagRepository.php` (`namespace
    App\Modules\Tags\Repository;`, импорты: `Tag`, `TagCollection`, `TagText` из
    `Tags`; `TagId` из `App\Shared\Domain\ValueObject`; `AbstractRepository` из
    `Shared`; `Parameter` из Cycle — без изменений по логике).
  - `app/src/Modules/Tags/Domain/Entity/Tag.php` (`namespace
    App\Modules\Tags\Domain\Entity;`, импорты: `TagText` из `Tags\Domain\ValueObject`;
    `TagId` из `App\Shared\Domain\ValueObject`; `TagRepository` из `Tags\Repository`;
    `UserId`, `HasTimestamps`, `ValueObjectCast` из `Shared`; атрибут `#[Entity(role:
    'tag', table: 'tags', repository: TagRepository::class, …)]` без изменений). В
    `#[Column]` атрибут typecast ссылается на новые FQCN: `typecast: TagId::class` →
    `App\Shared\Domain\ValueObject\TagId`, `typecast: TagText::class` →
    `App\Modules\Tags\Domain\ValueObject\TagText` (отдельные typecast-классы не нужны).
- Обновить связь в `Posts` (заменить импорт `TagId` на `App\Shared\Domain\ValueObject\TagId`
  в ОБОИХ файлах, иначе после удаления старого `TagId` сломается autoload/PHPStan):
  - `app/src/Modules/Posts/Domain/Entity/PostTag.php` — заменить
    `use App\Modules\Posts\Domain\ValueObject\TagId;` на
    `use App\Shared\Domain\ValueObject\TagId;` (остальное без изменений; `PostTagId`
    остаётся в `Posts`).
  - `app/src/Modules/Posts/Repository/PostTagRepository.php` — заменить
    `use App\Modules\Posts\Domain\ValueObject\TagId;` на
    `use App\Shared\Domain\ValueObject\TagId;` (используется в `findByTagId(TagId)`;
    `PostId`, `PostTag`, `PostTagCollection` остаются в `Posts`).
- Удалить старые файлы тегов из `Posts`:
  `app/src/Modules/Posts/Domain/Entity/Tag.php`,
  `app/src/Modules/Posts/Domain/ValueObject/TagText.php`,
  `app/src/Modules/Posts/Domain/ValueObject/TagId.php`,
  `app/src/Modules/Posts/Domain/Collection/TagCollection.php`,
  `app/src/Modules/Posts/Repository/TagRepository.php`.
- Обновить импорты во ВСЕХ тестах, ссылающихся на перемещённые классы (файлы пока
  остаются на месте, меняются только `use`):
  - `tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php`: `Tag`, `TagText` →
    `Tags`; `TagId` → `Shared`.
  - `tests/Unit/Modules/Posts/Domain/ValueObject/PostsIdentifierTest.php`: `TagId` →
    `Shared`.
  - `tests/Unit/Modules/Posts/Domain/ValueObject/PostsTextValueObjectTest.php`:
    `TagText` → `Tags`.
  - `tests/Unit/Modules/Posts/Domain/Collection/PostsCollectionTest.php`:
    `TagCollection` → `Tags`.
  - `tests/Feature/Modules/Posts/PostsRepositoryTestCase.php`: импорт `TagRepository`
    → `App\Modules\Tags\Repository\TagRepository` (метод `tagRepository()` пока
    остаётся, удаляется в фазе 2).
  - `tests/Feature/Modules/Posts/Repository/TagAndPostTagRepositoryTest.php`: `Tag`,
    `TagText` → `Tags`.
  - `tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php`: `Tag`, `TagText`
    → `Tags`.

Результат: словарь тегов работает из `App\Modules\Tags`, `TagId` — из `Shared`; в
`Posts` нет tag-классов, кроме `PostTag`; зависимости `Posts\Domain → Tags\Domain`
нет; раскладка тестовых файлов прежняя.

Сценарии тестирования (после реализации фазы):
- весь существующий набор тестов проходит из новых namespace (перенос ничего не сломал);
- `TagRepository` резолвится из контейнера по новому FQCN;
- `PostTag::create(PostId, TagId)` работает с `TagId` из `Shared`;
- уникальность `tags.text` и FK `post_tags.tag_id → tags.id`, `tags.created_by_id →
  users.id` по-прежнему срабатывают (покрыто `TagAndPostTagRepositoryTest`).

Проверка:
- `make phpstan` (level max) — нет неразрешённых символов/импортов;
- `make test` — зелёный;
- `grep -rn "Modules\\\\Posts\\\\Domain\\\\ValueObject\\\\TagId\|Modules\\\\Posts\\\\Domain\\\\Entity\\\\Tag\b\|Modules\\\\Posts\\\\Repository\\\\TagRepository\|Modules\\\\Posts\\\\Domain\\\\Collection\\\\TagCollection\|Modules\\\\Posts\\\\Domain\\\\ValueObject\\\\TagText" app/ tests/`
  не находит ссылок на старые расположения.

### 2. Приведение тестов к границам модулей

Цель: перенести tag-тесты под `Tests\…\Modules\Tags`, оставив в `Posts` только тесты
постов и связи `PostTag`, чтобы тестовая раскладка соответствовала границам модулей и
сохранялось 100% покрытие.

Что сделать:
- Unit-тесты модуля `Tags` (создать):
  - `tests/Unit/Modules/Tags/Domain/Entity/TagEntityTest.php` — перенести `testTagCreate`
    из `JoinEntityTest` (создание `Tag`, проверка UUID v7, `text`, `createdById`).
  - `tests/Unit/Modules/Tags/Domain/ValueObject/TagTextValueObjectTest.php` — перенести
    из `PostsTextValueObjectTest` методы про тег: нормализация в нижний регистр,
    отклонение пробела, спецсимвола, пустого значения, превышения длины (51 символ).
  - `tests/Unit/Modules/Tags/Domain/Collection/TagCollectionTest.php` — перенести
    проверку `TagCollection` (пустая коллекция / count) из `PostsCollectionTest`.
- Очистить unit-тесты `Posts`:
  - `JoinEntityTest`: удалить `testTagCreate` и импорты `Tag`, `TagText`; оставить
    импорт `TagId` из `Shared` (он нужен тесту `PostTag`).
  - `PostsIdentifierTest`: убрать `TagId` из списка `idClasses` и его импорт (`TagId`
    как пустой наследник покрыт `AbstractUuidV7IdTest`, как `UserId`).
  - `PostsTextValueObjectTest`: удалить tag-методы и импорт `TagText`.
  - `PostsCollectionTest`: удалить проверку `TagCollection` и её импорт.
- Feature-тесты модуля `Tags` (создать):
  - `tests/Feature/Modules/Tags/TagsRepositoryTestCase.php` — компактная база
    (`extends Tests\DatabaseTestCase`) с минимально необходимым набором, сверенным с
    `DatabaseTestCase`/`PostsRepositoryTestCase`: `createUser()` с собственным
    счётчиком (`userCounter`) для уникальных `email`/`nickname`, `persist()` с
    немедленным flush, доступ к `entityManager()` и `cleanOrmHeap()` (из
    `DatabaseTestCase`), аксессор `tagRepository(): TagRepository` (резолв
    `App\Modules\Tags\Repository\TagRepository` из контейнера). `createUser` создаёт
    реальную строку `users` для FK `tags.created_by_id`. Дублирование `createUser`/
    `persist` с `PostsRepositoryTestCase` сознательное — изоляция тестовой
    инфраструктуры модулей (отметить для ревью; общую базу не выносим, чтобы не
    расширять область задачи и не связывать тест-инфраструктуру модулей).
  - `tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php` — перенести из
    `TagAndPostTagRepositoryTest` тесты тега: `testStoresAndRestoresTag`,
    `testFindByTexts`, `testTagTextIsUnique`, `testCannotDeleteUserReferencedByTag`.
- Feature-тесты `Posts` (реорганизовать):
  - Создать `tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php` с
    тестами связи: `testPostTagLookups`, `testPostTagIsUniquePerPostAndTag`,
    `testCannotDeleteTagReferencedByPostTag` и хелпером `createPostFor`. Для создания
    тегов-фикстур импортировать `Tag`, `TagText` из `App\Modules\Tags` (кросс-модульная
    ссылка в тестовых фикстурах допустима — правило Application-границы относится к
    production-коду, а FK `post_tags.tag_id → tags.id` требует реальной строки `tags`).
  - Удалить `tests/Feature/Modules/Posts/Repository/TagAndPostTagRepositoryTest.php`.
  - `PostRepositoryTest`: оставить в `Posts`; его кросс-модульные импорты `Tag`/
    `TagText` из `App\Modules\Tags` (фикстура тега в каскадном тесте, см. строку ~247)
    НЕ убирать — это тестовая фикстура для FK, реальная строка `tags` нужна для
    проверки каскада удаления поста. Изменений в фазе 2 этот файл не требует (импорты
    уже переведены в фазе 1).
  - `PostsRepositoryTestCase`: перед удалением метода `tagRepository()` проверить
    `grep -rn "tagRepository()" tests/`, что других потребителей, кроме удаляемого
    `TagAndPostTagRepositoryTest`, нет; затем удалить метод `tagRepository()` и импорт
    `TagRepository` (модуль `Posts` больше не владеет репозиторием тегов; `PostTag`-тест
    создаёт теги через общий `persist()`). Оставить `postTagRepository()`.

Результат: tag-тесты под `Tests\…\Modules\Tags`, в `Posts` — только посты и `PostTag`;
покрытие тегов сохранено; покрытие 100%.

Сценарии тестирования (после реализации фазы):
- `Tag::create` и невалидные/валидные `TagText` покрыты тестами модуля `Tags`;
- `TagRepository` (`findById`/`findByText`/`findByTexts`), уникальность `tags.text`,
  RESTRICT на `tags.created_by_id` покрыты feature-тестами `Tags`;
- связь `PostTag` (поиск по посту/тегу, уникальность `(post_id, tag_id)`, RESTRICT на
  `post_tags.tag_id`) покрыта feature-тестами `Posts`;
- ни один tag-тест не потерян при переносе.

Проверка:
- `make phpstan` — зелёный;
- `make test` — зелёный;
- отчёт покрытия — 100% (`make test-coverage`/`make qa`, как принято в проекте);
- `find tests -path '*Modules/Posts*' -name '*Tag*'` показывает только
  `PostTagRepositoryTest.php` (нет «голых» tag-тестов в `Posts`);
- `grep -rn "tagRepository\|TagAndPostTagRepositoryTest" tests/Feature/Modules/Posts`
  не находит остатков.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза завершается прогоном `make phpstan` и
`make test` (все через Docker), suite остаётся зелёным на границе фаз. Фаза 1 проверяет,
что перенос production не сломал существующие тесты (раскладка файлов прежняя). Фаза 2
проверяет границы модулей и 100% покрытие после переразбиения тестов. Новых типов
тестов не вводится — существующие unit/feature тесты тегов переносятся без потери
сценариев; добавляется только разнесение по модулям и компактная база
`TagsRepositoryTestCase`. Команды: `make test`, `make phpstan`, отчёт покрытия —
`make test-coverage`/`make qa` (как в памяти проекта про гейт покрытия).

## Логирование

Стратегия: `debug_precise`. Задача — перенос DB-слоя (Entity, VO, коллекция,
read-only репозиторий); рантайм-логирование в этих классах отсутствует и не
добавляется (репозитории только читают, доменные типы не логируют). Новых логов
вводить не нужно — это сохраняет соответствие `rules.md` («бизнес-логика логирует на
DEBUG», INFO только для ключевых бизнес-событий): здесь бизнес-событий нет. Когда
позже появится Application-слой `Tags` (отложено), его сценарии будут логировать по
`debug_precise` — точечный DEBUG на старте/ветвлениях use-case; это вне объёма
текущего плана.

## Документация и эксплуатация

- `env`/runbook не меняются: новых переменных окружения, конфигов, очередей и
  маршрутов нет.
- Схема БД структурно не меняется — отдельной миграции и backfill не требуется.
- Кэш схемы Cycle: `make test` сам сбрасывает и пересобирает схему (цепочка
  `reset-test` → миграции тест-БД → `warmup`), поэтому при проверках через `make test`
  устаревший кэш не возникает. Риск устаревшего кэша есть только при ручном
  `vendor/bin/phpunit`/в проде (`CYCLE_SCHEMA_CACHE=true` по умолчанию в `cycle.php`):
  там после переноса сущностей обновить кэш командой `php app.php cycle` (роли/таблицы
  те же, но FQCN сущностей изменились).
- Изменения относятся к незакоммиченной ветке `work-1`; коммит и его сообщение —
  отдельным шагом (вне объёма плана), на русском по Conventional Commits.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** в фазу 1 — правка `app/src/Modules/Posts/Repository/PostTagRepository.php`
  (импорт `TagId` → `Shared`); это критичный пропуск исходного инвентаря — файл
  использует `TagId` в `findByTagId()`, без правки ломался бы autoload/PHPStan после
  удаления старого `TagId`.
- **+ Добавлено:** в фазу 1 — явные новые FQCN в `#[Column(typecast: …)]` сущности
  `Tag` (`TagId` → `Shared`, `TagText` → `Tags`).
- **+ Добавлено:** в контекст — пояснение цепочки резолва репозитория Cycle (через
  атрибут `repository:`), чтобы `$container->get(TagRepository::class)` работал после
  смены FQCN.
- **+ Добавлено:** в фазу 2 — явная легализация кросс-модульных фикстур `Tag`/`TagText`
  в `PostRepositoryTest` (не убирать) и проверка `grep "tagRepository()"` перед
  удалением метода из `PostsRepositoryTestCase`.
- **~ Изменено:** уточнён конкретный состав `TagsRepositoryTestCase` (`createUser` со
  счётчиком, `persist`, `entityManager`/`cleanOrmHeap`, `tagRepository`), сверенный с
  `DatabaseTestCase`/`PostsRepositoryTestCase`.
- **~ Изменено:** раздел эксплуатации — `make test` сам сбрасывает кэш схемы Cycle;
  ручной `php app.php cycle` нужен только для ручного phpunit/прода.
- **Отклонено:** вынос общей базы `AbstractModuleRepositoryTestCase` вместо
  дублирования `createUser`/`persist` в `TagsRepositoryTestCase` и
  `PostsRepositoryTestCase` — отклонено, чтобы не расширять область задачи и не
  связывать тест-инфраструктуру модулей; дублирование сознательное и помечено для ревью
  (подтверждено флагманским ревьюером как приемлемое).

## Прогресс выполнения
Журнал: `docs/executions/2026-06-17_19-29_extract-tags-module.md`

- [x] Фаза 1: Перенос словаря тегов в `Tags` и `TagId` в `Shared`
- [x] Фаза 2: Приведение тестов к границам модулей
- [x] Финальная проверка: `make qa` (стиль + `make phpstan` + покрытие) — зелёный, покрытие 100.00%
