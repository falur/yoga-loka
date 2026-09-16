---
title: Волна E переезда на целевую архитектуру — домен отделён от хранения
date: 2026-09-16 10:40
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: end_of_plan
logging_strategy: standard
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references:
    - docs/references/entity.md
    - docs/references/cycle-entity.md
    - docs/references/mapper.md
    - docs/references/entity-columns.md
    - docs/references/typecast.md
    - docs/references/cycle-repository.md
    - docs/references/repository.md
    - docs/references/value-object.md
    - docs/references/domain-collection.md
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Задачи roadmap 13 и 14 (`docs/artifacts/roadmaps/2026-09-15_17-16_migraciya-na-celevuyu-arhitekturu.md`): у всех 29 доменных сущностей убрать разметку Cycle. После волны ни один класс `Modules/{M}/Domain/Entity/*` не импортирует `Cycle\*`, не несёт `#[Entity]`/`#[Column]`/relation-атрибуты, не ссылается на свой Cycle-репозиторий и не использует Cycle-версию трейта таймстемпов. Форма хранения каждой сущности описана `Cycle{Name}Entity`, преобразование — `{Name}Mapper`, имена таблицы/колонок — `{Entity}Columns`, `Cycle{Name}Repository` работает только с Cycle Entity и Mapper.

Дополнительно закрывается хвост волны D (`docs/artifacts/executions/2026-09-16_00-47_volna-d-hranenie-i-oshibki-u-vladelca.md`, «Незакрытое»): `CycleTokenStorage` реализует одновременно `Spiral\Auth\TokenStorageInterface` и `AuthTokenStorageContract` и разделяется на два адаптера.

Границы (заданы в вызове, не пересматриваются): внешнее поведение (маршруты, формы запросов/ответов, ошибки, переводы) не меняется; схема БД не меняется вообще — новых миграций нет, `Cycle{Name}Entity` описывает то же хранение, что сегодня `#[Entity]`/`#[Column]`; `Reader`/`Data`/снос `Application/View` — задачи 15/16, не эта волна; расположение миграций и тестов — задачи 20/24, не эта волна; границы транзакций и число прогонов `EntityManager` в каждом сценарии не меняются (волна D уже один раз ловила регрессию по этому пункту).

Режим проверок изменён пользователем на эту волну: фазы 1-8 не запускают `make qa`/`make test`/`make phpstan`/`make test-unit`; проверяющий фазы читает код и сверяет с планом и карточками `docs/references/`. Полный `make qa` запускается один раз — в фазе 9; при падении причина чинится точечно в этой же волне до зелёного результата, волна не переигрывается целиком.

Известное падение `Tests\Feature\Modules\Media\Infrastructure\S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` существует с начала переезда, в задачу не входит и не чинится.

## Целевой алгоритм

### Разделение одной сущности (применяется одинаково ко всем 29)

| Шаг | Результат |
|---|---|
| 1. `{Entity}Columns` в `Infrastructure/Persistence/Cycle/Columns` | `final class`, закрытый конструктор; `TABLE` — имя таблицы из текущего `#[Entity(table:)]`, далее константы колонок из `#[Column(name:)]`/имени свойства — только реально используемые в запросах и в Cycle Entity |
| 2. `Cycle{Name}Entity` в `Infrastructure/Persistence/Cycle/Entity` | Переносит `#[Entity(role:, table:, repository:, typecast:)]`, все `#[Column]`, relation-атрибуты (`HasMany`/`BelongsTo`/`RefersTo`) и `use HasTimestamps` (Cycle-версия) один в один с прежней доменной сущности; `table:`/имена колонок — через `{Entity}Columns`; `repository:` указывает на `Cycle{Name}Repository` |
| 3. `Domain/Entity/{Name}` | Убрать `#[Entity]`, все `#[Column]`, relation-атрибуты, Cycle-версию `HasTimestamps`, импорты `Cycle\*`, ссылку на свой `Cycle{Name}Repository`; добавить `restore(...)` — сегодня Cycle гидратировал доменный класс напрямую, поэтому `restore()` у большинства сущностей ещё не существует |
| 4. `{Name}Mapper` в `Infrastructure/Persistence/Cycle/Mapper` | `toDomain(Cycle{Name}Entity): {Name}` вызывает `{Name}::restore(...)`; `toCycleEntity({Name}, Cycle{Name}Entity\|null): Cycle{Name}Entity` заполняет/обновляет Cycle Entity; внутренние сущности агрегата, приходящие через relation-коллекцию, конвертируются приватным методом Mapper-а корня либо отдельным `{Name}Mapper` — по `mapper.md`, «Допустимые варианты» |
| 5. `Cycle{Name}Repository` | Работает с `Cycle{Name}Entity`, условия выборки — через `{Entity}Columns`, каждый объект проходит через `{Name}Mapper`; наружу (в `Domain/Repository/{Name}Repository`, форма которого зафиксирована волной D и не меняется) — только `{Name}`, `{Name}Collection`, `null`, `bool`, `void` или счётчик |

### Правило переноса значений колонок (снимает главный риск волны)

Сегодня колонки используют либо `typecast: {ПростойVO}::class` (общий диспетчер `App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast`, вызывающий `fromString()`/`fromInt()`/`BackedEnum::from()`), либо `typecast: {X}Typecast::class` (39 классов на `App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast`, статические `castDatabaseValue()`/`uncastValue()`). Карточка `typecast.md`: «Domain ValueObject создаёт Mapper, а не Typecast»; Typecast — только для «составного JSON, шифрования или формата, который нельзя однозначно выразить типом колонки». Правило:

- **Колонка = один примитив, однозначно восстанавливаемый существующим методом VO** (`fromString()`/`fromInt()`/`fromNullable()`/`BackedEnum::from()`, на запись — `->value()`/`->value`) — сюда попадает и сегодняшний `ValueObjectCast`-путь, и те из 39 `{X}Typecast`, чьи методы — ровно такой вызов (часто из-за null-bridging: nullable-колонка ↔ non-nullable VO с сентинелом). `Cycle{Name}Entity` получает нативное поле (`string`/`int`/`bool`/`\DateTimeImmutable`/`BackedEnum`, `\|null` при nullable) без `typecast:`; вызов переезжает как есть в `{Name}Mapper`; сам `{X}Typecast`-класс и его тест удаляются как код, ставший неиспользуемым именно этим изменением (`docs/rules.md`), логика при переносе не меняется.
- **Колонка = составная структура, которую Cycle обязан собрать/разобрать раньше Mapper-а** (JSON-коллекция/объект — определяется чтением тела конкретного класса) — Typecast остаётся как есть на `#[Column]` `Cycle{Name}Entity`; если он возвращает доменный тип напрямую, поле Cycle Entity типизируется этим типом, Mapper передаёт значение без изменений (как сегодня с `\DateTimeImmutable`).
- `ValueObjectCast`/`ColumnValueTypecast` не переписываются, пока на них ссылается хоть один `Cycle{Name}Entity`; когда по итогам 8 модулей ни один `#[Column]` не указывает `typecast: {ПростойVO}::class`, фаза 9 проверяет остаточных потребителей и удаляет `ValueObjectCast` вместе с тестом, если потребителей не осталось.

Классификацию каждого `{X}Typecast` делает исполнитель фазы чтением его тела. Схема колонки не меняется ни в одном случае — меняется только слой (Typecast vs Mapper), где происходит тот же вызов метода VO.

### Внутренние сущности агрегата и связи

`HasMany`/`BelongsTo`/`RefersTo` (в т.ч. на внутренних сущностях — `PostMedia`, `PostTag`, `PostMention`, `PostLike`, `CommentLike`, `CommentMention`, `Media{Image,Video,Audio}Conversion`, `MediaMultipartUpload`, `RolePermission`) переезжают на `Cycle{Name}Entity` без изменения `target`/`innerKey`/`outerKey`/`orderBy`/каскадов/`collection:` — меняются только имена классов на `Cycle*Entity`. Каждая внутренняя сущность получает свой `Cycle{Name}Entity`, `{Entity}Columns` и Mapper-преобразование (класс либо приватный метод корневого Mapper-а). Итого 29 `Cycle{Name}Entity` и 29 `{Entity}Columns`; число файлов `{Name}Mapper` может быть меньше 29. Домен-коллекции (`PostMediaCollection` и т.п.) не меняются.

### Трейт таймстемпов

`App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps` (поля с `#[Column]`, `initializeTimestamps()`/`touch()`) переезжает на `Cycle{Name}Entity` без изменений. Но `createdAt`/`updatedAt` сегодня читаются Application-слоем напрямую с доменных сущностей (`$post->createdAt`, `$notification->createdAt`, `$activeCode->createdAt` — подтверждено `grep` в Auth/Notifications/Posts), поэтому домен не может остаться без этих полей. Заводится `App\Shared\Domain\Trait\HasTimestamps` — тот же состав полей и методов, но без `#[Column]` и без `Cycle\*`; доменные сущности переключаются на него. `{Name}Mapper` копирует `createdAt`/`updatedAt` как обычные поля `\DateTimeImmutable` без преобразования.

### Разделение `CycleTokenStorage`

Сегодня один `CycleTokenStorage implements Spiral\Auth\TokenStorageInterface, AuthTokenStorageContract` (`Auth/Infrastructure/Spiral/Auth/CycleTokenStorage.php`) закрывает и vendor-границу (`load()`/`create()`/`delete()`), и доменную (`issuePair()`/`rotate()`/`revokeSession()`/`revokeUserSession()`). Делится на два класса в `Infrastructure/Spiral/Auth`: `SpiralTokenStorage implements Spiral\Auth\TokenStorageInterface` (`load()`/`create()`/`delete()`) и `AuthTokenIssuer implements AuthTokenStorageContract` (`issuePair()`/`rotate()`/`revokeSession()`/`revokeUserSession()`). Общий приём выпуска токена (генерация значения, `AuthToken::issue()`, `$authTokenRepository->save()`, сборка `AuthTokenView`) выносится в непубликуемый класс `AuthTokenIssuing` (final, без интерфейса), которым пользуются оба адаптера — не дублируется. Число прогонов `EntityManager` не меняется. `AuthBootloader` регистрирует оба адаптера отдельно: `AuthTokenStorageContract::class => AuthTokenIssuer::class`, `addTokenStorage(name: 'cycle', storage: SpiralTokenStorage::class)`.

## Контракты реализации

### Данные и БД

Не затрагивается. Миграции не создаются и не редактируются. `Cycle{Name}Entity` описывает ровно те таблицы/колонки/типы/индексы/FK/каскады, что сегодня `#[Entity]`/`#[Column]`/relation-атрибуты домена — критерий приёмки фазы 9: снимок схемы БД до и после волны идентичен.

### API и внешние контракты

Не затрагивается. Маршруты, Filter, Resource, Response, коды ошибок, переводы не меняются — Application и Infrastructure/Spiral в волну не входят, кроме связывания `CycleTokenStorage`-адаптеров в `AuthBootloader` (меняется биндинг, не форма ответа).

## Фазы выполнения

Каждая фаза — один модуль, все его сущности (корневые и внутренние агрегата) переводятся разом: внутренние сущности не имеют жизненного цикла отдельно от корня. Порядок — от простого к сложному, как задано в вызове: Tags → Access → User → Auth (+ разделение `CycleTokenStorage`) → Outbox → Notifications → Media → Posts → приёмка.

Каждая фаза выполняется одним исполнителем и проверяется одним проверяющим без `make qa`/`make test`/`make phpstan`/`make test-unit` — проверяющий читает код и построчно сверяет с этим планом, с «Целевым алгоритмом» и карточками (`entity.md`, `cycle-entity.md`, `mapper.md`, `entity-columns.md`, `typecast.md`, `cycle-repository.md`, `repository.md`), и подтверждает, что число вызовов `$entityManager->run()`/`persist()` в задействованных сценариях не изменилось (`git diff`/`git show HEAD:`).

### 1. Tags

Цель: полный цикл разделения на одной простой независимой сущности `Tag`, чтобы зафиксировать образец для фаз 2-8.

Что сделать: `Tag` (таблица `tags`, 3 колонки без таймстемпов, без relations) переводится по «Целевому алгоритму»: `TagColumns`, `CycleTagEntity`, `TagMapper`, `Tag::restore()`, обновлённый `CycleTagRepository`. `Tags/Application` не меняется — продолжает получать `Tag`/`TagCollection` из того же интерфейса.

Результат: `Tag.php` не импортирует `Cycle\*`.

Проверка: построчное чтение пяти изменённых файлов против «Целевого алгоритма» и карточек; сверка колонок `tags` с миграцией `20260617.160942_0_create_posts_domain_tables.php`; `grep` подтверждает отсутствие строковых литералов имён таблицы/колонок в `CycleTagRepository` вне `TagColumns`.

### 2. Access

Цель: повторить образец на четырёх сущностях с двумя агрегатами (`Role` с внутренней `RolePermission`, `UserRole`) и на репозиториях, унаследованных от `Cycle\ORM\Select\Repository` напрямую.

Что сделать: `Role`, `Permission`, `UserRole`, `RolePermission` переводятся по «Целевому алгоритму»: четыре `{Entity}Columns`, четыре `Cycle*Entity`, `RoleMapper` (включает конвертацию вложенной коллекции прав), `PermissionMapper`, `UserRoleMapper`, три `Cycle*Repository`.

Результат: `Access/Domain/Entity/*` не импортирует `Cycle\*`.

Проверка: построчное чтение изменённых файлов; сверка колонок `roles`, `permissions`, `role_permissions`, `user_roles` (включая FK) с миграцией `20260613.143902_0_create_access_domain_tables.php`; условия `RoleRepository::findPermissions()`/`hasPermission()` (волна D, решение №12) не изменились.

### 3. User

Цель: повторить образец на трёх независимых корнях (`User`, `UserBan`, `ReservedNickname`) — первая фаза с массовым применением «Правила переноса значений колонок» (10 typecast-классов).

Что сделать: три сущности переводятся по «Целевому алгоритму». Каждый из десяти `{X}Typecast` User (`BanExpirationTypecast`, `BanUnbannedAtTypecast`, `BanUnbannedByTypecast`, `BanUnbannedReasonTypecast`, `ReservedNicknameHolderTypecast`, `UserAvatarTypecast`, `UserBioTypecast`, `UserDeletionTypecast`, `UserLocationTypecast`, `UserSpiritualNameTypecast`) классифицируется исполнителем по телу; для сущностей на `typecast: {ПростойVO}::class` без выделенного класса Mapper вызывает те же фабрики, что раньше `ValueObjectCast`.

Результат: `User/Domain/Entity/*` не импортирует `Cycle\*`; ни один из десяти typecast-классов не остаётся неиспользуемым (либо удалён, либо применяется на Cycle Entity).

Проверка: построчное чтение изменённых файлов, включая явную сверку категории каждого из 10 typecast-классов и целостности перенесённой логики; сверка колонок `users`, `user_bans`, `reserved_nicknames` с миграцией `20260613.143901_0_create_user_domain_tables.php`; сигнатура `UserBanRepository` не изменилась.

### 4. Auth

Цель: повторить образец на трёх независимых корнях (`AuthToken`, `LoginCode`, `RegistrationTicket`) и разделить `CycleTokenStorage`.

Что сделать: три сущности переводятся по «Целевому алгоритму», включая перенос четырёх typecast-классов (`ConsumptionTypecast`, `ExpirationTypecast`, `IpTypecast`, `UserAgentTypecast`) по правилу категоризации фазы 3. Отдельно: `SpiralTokenStorage`, `AuthTokenIssuer`, `AuthTokenIssuing` создаются по «Разделению CycleTokenStorage»; `AuthBootloader` обновляет оба биндинга; `CycleTokenStorage.php` удаляется. Пара `add()`/`save()` в `LoginCodeRepository`/`RegistrationTicketRepository` (решение №15 волны D) сохраняется без изменения сигнатур — число прогонов `EntityManager` в `RequestLoginCodeHandler` не меняется; решение №16 «Незакрытого» волны D (сведение к одному методу) этой волной не пересматривается.

Результат: `Auth/Domain/Entity/*` не импортирует `Cycle\*`; `CycleTokenStorage` не существует; `TokenStorageInterface` и `AuthTokenStorageContract` реализованы разными классами.

Проверка: построчное чтение изменённых файлов; сверка колонок `auth_tokens`, `auth_login_codes`, `auth_registration_tickets` с миграциями `20260615.141700_0_create_auth_domain_tables.php`, `20260616.180010_0_add_device_to_auth_tokens.php`; построчная сверка `SpiralTokenStorage`/`AuthTokenIssuer`/`AuthTokenIssuing` с прежним `CycleTokenStorage` — каждый метод перенесён без изменения тела; `AuthBootloader` содержит оба новых биндинга.

### 5. Outbox

Цель: повторить образец на единственной сущности `StoredOutboxEvent` с парой `add()`/`save()` (решение №15 волны D) и составным JSON-typecast (`OutboxEventPayloadTypecast`).

Что сделать: `StoredOutboxEvent` переводится по «Целевому алгоритму»; `OutboxEventPayloadTypecast`, `OutboxAvailableAtTypecast`, `OutboxEventDateTypecast`, `OutboxLastErrorTypecast` разбираются по правилу категоризации (`OutboxEventPayloadTypecast` — вероятный кандидат «составной JSON»). Пара `add()`/`save()` сохраняется без изменения сигнатур.

Результат: `Outbox/Domain/Entity/StoredOutboxEvent` не импортирует `Cycle\*`.

Проверка: построчное чтение изменённых файлов; сверка колонок `outbox_events` с миграцией `20260525.153700_0_create_outbox_events_table.php`; `StoredOutboxEventRepository::add()` по-прежнему без прогона, `save()` — ровно один, как до фазы.

### 6. Notifications

Цель: повторить образец на трёх независимых корнях (`Notification`, `NotificationSetting`, `NotificationDeviceToken`) и на порте массовой записи `CycleMarkAllNotificationsRead`.

Что сделать: три сущности переводятся по «Целевому алгоритму», пять typecast-классов Notifications разбираются по правилу категоризации. `CycleMarkAllNotificationsRead` переводится на имена колонок из `NotificationColumns` вместо строковых литералов; собственный SQL и сигнатура `markAllReadForRecipient(UserId, \DateTimeImmutable): int` не меняются — это порт прямой массовой записи (`SetBasedWrite`), а не Repository агрегата, он не становится потребителем Cycle Entity/Mapper.

Результат: `Notifications/Domain/Entity/*` не импортирует `Cycle\*`.

Проверка: построчное чтение изменённых файлов; сверка колонок `notifications`, `notification_settings`, `notification_device_tokens` с миграцией `20260613.130000_0_create_notification_domain_tables.php`; `CycleMarkAllNotificationsRead` — SQL и сигнатура те же, только литералы заменены на `NotificationColumns::*`.

### 7. Media

Цель: повторить образец на агрегате с наибольшим числом внутренних сущностей (`Media` — корень; `Media{Image,Video,Audio}Conversion`, `MediaMultipartUpload` — внутренние, `ON DELETE CASCADE`) и составными JSON/бинарными typecast-классами.

Что сделать: пять сущностей переводятся по «Целевому алгоритму»; три `HasMany`-связи корня и каскады переезжают на `CycleMediaEntity` без изменений. Четыре typecast-класса Media (`MediaMultipartPartCollectionTypecast`, `MediaWaveformTypecast`, `MediaProcessingErrorTypecast`, `MediaExpirationTypecast`) разбираются по правилу категоризации — первые три вероятные кандидаты «составного JSON». Единый доменный интерфейс `MediaRepository` (волна D) и границы записи семи Command handler-ов (`save()`/`delete()`/`saveAll()`/`saveWithMultipartUpload()`/`saveWithConversions()`, решение №23 волны D) сохраняются буквально — меняется внутреннее устройство `CycleMediaRepository`, не его внешние методы.

Результат: `Media/Domain/Entity/*` не импортирует `Cycle\*`.

Проверка: построчное чтение изменённых файлов; сверка колонок и FK `media`, `media_image_conversions`, `media_video_conversions`, `media_audio_conversions`, `media_multipart_uploads` с миграциями `20260521.184100_0_create_media_domain_tables.php`, `20260620.222100_0_create_media_audio_conversions_table.php`; построчная сверка семи Command handler-ов — набор и порядок вызовов `MediaRepository` не изменился (`git diff`).

### 8. Posts

Цель: повторить образец на трёх агрегатах (`Post` с `PostMedia`/`PostTag`/`PostMention`/`PostLike`, `Comment` с `CommentLike`/`CommentMention`, независимый `PostBlock`) и на кросс-корневых сценариях с парой `add()`/`save()` (решение №29 волны D).

Что сделать: девять сущностей переводятся по «Целевому алгоритму»; relations `Post -> {PostMedia,PostTag,PostMention,PostLike}` и `Comment -> {CommentLike,CommentMention}` переезжают на `CyclePostEntity`/`CycleCommentEntity` без изменений (связь `PostMedia -> Media` уже снята волной C, `PostMediaReference` остаётся доменной ссылкой и не восстанавливается как Cycle-relation). Двенадцать typecast-классов Posts разбираются по правилу категоризации (`PostOriginalTypecast`, `PostLessonTypecast`, `PostPracticeTypecast` — вероятные кандидаты «составного JSON»). Границы записи (`saveWithAttachments`, `saveRepost`/`saveWithOriginal`, `saveWithLike`, `removeLike`, `add()`/`addWithMentions()`+`save()`) и число прогонов `EntityManager` в 11 Command handler-ах, `PostContentComposer`, `CommentComposer` сохраняются буквально.

Результат: `Posts/Domain/Entity/*` не импортирует `Cycle\*`.

Проверка: построчное чтение изменённых файлов; сверка колонок и FK `posts`, `comments`, `post_likes`, `comment_likes`, `post_mentions`, `comment_mentions`, `post_media`, `post_tags`, `post_blocks` с миграцией `20260617.160942_0_create_posts_domain_tables.php` (без таблицы `tags` — она у Tags, переведена в фазе 1); построчная сверка изменённых Command/Query handler-ов, `PostContentComposer`, `CommentComposer` — число и место вызовов методов `PostRepository`/`CommentRepository`/`PostBlockRepository` не изменилось (`git diff`), включая пару `add()`/`save()`.

### 9. Приёмка волны

Цель: подтвердить, что все 29 сущностей переведены, схема БД не изменилась, поведение то же, и провести единственный за волну полный прогон `make qa`; при падении — исправить причину точечно и прогнать снова.

Что сделать: подтверждается отсутствие `Cycle\*`-импортов в `Domain/Entity` всех модулей, наличие 29 `Cycle*Entity`, 29 `{Entity}Columns` с `TABLE`, отсутствие строковых литералов имени таблицы/колонки в Cycle-репозиториях и в `CycleMarkAllNotificationsRead` (кроме имени таблицы/колонки, взятых из `NotificationColumns`). Проверяется судьба `ValueObjectCast`/`ColumnValueTypecast`/удалённых `{X}Typecast` по «Правилу переноса значений колонок» — если потребителей у `ValueObjectCast` не осталось, он и его тест удаляются. Схема БД сверяется снимком до/после волны (`pg_dump --schema-only` в Docker либо эквивалент — конкретная команда за исполнителем, критерий — побайтовое совпадение структуры). Запускается `make qa`; падения не на известном S3-тесте чинятся точечно в сущностях соответствующего модуля без отката к предыдущим фазам, `make qa` перезапускается. Проверяются `php app.php route:list` (33 маршрута, состав как в волне D) и генерация OpenAPI (побайтово равна снимку волны D).

Результат: волна закрыта, `make qa` зелёный кроме известного S3-падения, покрытие 100%, схема БД и маршруты не изменились; журнал волны явно закрывает четыре пункта «Незакрытого» волны D (разметка Cycle в домене, отсутствие `{Entity}Columns`, форма карточки `cycle-repository.md`, `CycleTokenStorage`) и перечисляет новое незакрытое, если есть.

Проверка:
- `make qa` зелёный (тесты/PHPStan/cs-fixer), единственное ожидаемое падение — `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`, покрытие 100.00%;
- `grep -rln "Cycle" app/src/Modules/*/Domain/Entity` пуст;
- `find app/src/Modules -path "*Infrastructure/Persistence/Cycle/Entity/Cycle*Entity.php"` — 29 файлов;
- `find app/src/Modules -path "*Infrastructure/Persistence/Cycle/Columns/*Columns.php"` — 29 файлов, у каждого есть `TABLE`;
- `pg_dump --schema-only` (или эквивалент) до/после волны совпадает по таблицам/колонкам/индексам/FK;
- `php app.php route:list` — 33 маршрута, тот же состав, что в волне D;
- `php app.php openapi:generate` — тот же md5, что зафиксирован волной D.

## Тесты

`test_strategy: end_of_plan` — единственный запуск тестов за волну в фазе 9. Фазы 1-8 переносят существующую логику (typecast, relations, границы записи, Repository) без изменения поведения — существующие тесты должны проходить без правок в большинстве случаев. Правки допускаются только там, где их требует структура кода: тест, инстанцировавший сущность через прямую Cycle-гидратацию (без вызова фабрики), после разделения обязан собирать объект через `{Name}::create()`/`restore()` или `{Name}Mapper` — перевод существующей проверки на новый способ построения объекта, не новый сценарий. Тесты удалённых typecast-классов первой категории переносятся на `{Name}MapperTest` без потери числа проверок (по образцу решения №32 волны D — пересчитывается и фиксируется в журнале при расхождении). Фаза 9 запускает `make test` в составе `make qa`; при падении — точечное исправление и повтор, без переигровки волны.

## Логирование

Изменений нет: логирование не относится к домену, Cycle Entity или Mapper, места логирования Application-слоя (уже на собственных типах исключений после волны D) не меняются. Если исполнитель обнаружит логирование внутри `Domain/Entity` (само по себе нарушение — `docs/arch.md` запрещает logger в Domain), он выносит вызов в Infrastructure по факту находки и фиксирует решение в журнале фазы.

## Документация и эксплуатация

- Карточки `cycle-entity.md`, `mapper.md`, `typecast.md`, `entity-columns.md`, `cycle-repository.md` не меняются: «Правило переноса значений колонок» — прикладное решение этой волны, не новое общее правило; пример карточки (`User`) остаётся корректным целевым образцом.
- README модулей актуализируются там, где описывают текущую разметку Cycle на доменной сущности или упоминают удалённые typecast-классы — по факту находки в каждой фазе.
- Журнал волны явно ссылается на четыре пункта «Незакрытого» волны D, которые закрывает эта волна (см. «9. Приёмка волны»).

## Принятые решения

1. **Режим проверок фаз отключён по прямому указанию пользователя.** `test_strategy: end_of_plan` вместо `plan.test_strategy: after_each_phase` из `docs/settings.yaml` — прямое указание в вызове важнее файла настроек. Источник: сообщение пользователя.
2. **Порядок фаз — по модулям от простого к сложному**, задано в вызове: Tags, Access, User, Auth, Outbox, Notifications, Media, Posts, приёмка. Источник: сообщение пользователя.
3. **Правило переноса значений колонок (простой VO → Mapper, составной формат → остаётся Typecast).** Карточка `typecast.md` прямо требует «Domain ValueObject создаёт Mapper, а не Typecast» и ограничивает Typecast составными форматами; тело каждого из 39 существующих `{X}Typecast` переносится без изменений логики, что не меняет наблюдаемое поведение и минимизирует риск. Источник: `docs/references/typecast.md`, разделы «Назначение», «Допустимые варианты»; autonomous.
4. **Cycle Entity скаляризует поле, кроме случаев, где значение приходит из составного Typecast.** Снимает противоречие между буквальным примером карточки `mapper.md` (скалярный Cycle Entity) и сегодняшним диспетчером `ValueObjectCast`: диспетчер не переписывается, но перестаёт быть основным путём для простых VO-колонок. Источник: `cycle-entity.md`, `mapper.md`, `typecast.md`; autonomous.
5. **`App\Shared\Domain\Trait\HasTimestamps` заводится в `Shared/Domain/Trait`** (без `#[Column]`), потому что `createdAt`/`updatedAt` сегодня читаются Application-слоем напрямую с доменных сущностей в Auth/Notifications/Posts (подтверждено `grep`) и не могут исчезнуть из домена. Источник: `docs/rules.md` («сущность не содержит атрибуты Cycle»), фактическое использование; autonomous.
6. **`CycleTokenStorage` делится на `SpiralTokenStorage`/`AuthTokenIssuer` в `Infrastructure/Spiral/Auth`**, а не в `Infrastructure/Persistence/Cycle`, как предполагала карта расхождений (`docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md:286-287`): класс не описывает форму хранения (это Cycle Entity/Mapper/Repository/Columns этой же волны), а оркеструет доменный `AuthTokenRepository` — по составу адаптер границы Auth, а не Cycle-специфичный код. `Infrastructure/Persistence/Cycle` по `docs/arch.md` зарезервирован за формой хранения. Источник: `docs/arch.md`; autonomous, карта не нормативна для этой развилки.
7. **Дублирующаяся логика выпуска токена выносится в непубликуемый `AuthTokenIssuing`**, а не копируется в оба адаптера — предотвращает рассинхронизацию без нарушения «один адаптер — один интерфейс». Источник: `docs/rules.md` (не дублировать), `docs/arch.md:161-162`; autonomous.
8. **Пары `add()`/`save()` (решения №15, №29 волны D) не пересматриваются** — волна 13/14 меняет форму хранения, а не границы транзакций; сведение к одному методу (решение №16 «Незакрытого» волны D) — отдельный вопрос вне буквальной задачи «домен отделён от хранения», совмещение увеличило бы риск регрессии по числу прогонов `EntityManager`. Источник: границы волны из вызова; autonomous.
9. **`CycleMarkAllNotificationsRead` не переводится на Cycle Entity/Mapper** — порт прямой массовой записи (`SetBasedWrite`, задача 12 roadmap), а не Repository агрегата; переносятся только имена таблицы/колонки на `NotificationColumns` (`docs/rules.md`). Источник: журнал волны D, «Массовая запись»; `docs/rules.md`; autonomous.
10. **Судьба `ValueObjectCast`/`ColumnValueTypecast` решается в фазе 9**, а не в каждой модульной фазе — только после перевода всех 8 модулей видно, остался ли потребитель. Источник: `docs/rules.md` («не оставляй мёртвый код, созданный текущим изменением») — применимо к финальному состоянию волны; autonomous.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-16_10-45_volna-e-otdelenie-domena-ot-hraneniya.md`. Режим: `subagents`.

| Фаза | Статус |
|---|---|
| 1. Tags | завершена, проверка passed |
| 2. Access | завершена, проверка passed |
| 3. User | завершена, проверка passed |
| 4. Auth | завершена, проверка passed |
| 5. Outbox | завершена, проверка passed |
| 6. Notifications | завершена, проверка passed |
| 7. Media | завершена, проверка passed (после 1 цикла исправления) |
| 8. Posts | завершена, проверка passed |
| 9. Приёмка волны | завершена, `make qa` зелёный |
