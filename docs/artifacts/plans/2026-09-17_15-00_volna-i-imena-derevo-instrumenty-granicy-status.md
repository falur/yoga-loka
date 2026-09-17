---
title: Волна I — имена, дерево, инструменты, границы, статус архитектуры
date: 2026-09-17 15:00
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: after_each_phase
logging_strategy: standard
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references:
    - docs/references/domain-service.md
    - docs/references/public-attribute.md
    - docs/references/bootloader.md
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Финальная волна переезда на целевую архитектуру закрывает задачи roadmap 25–30 и накопленный за волны A–H хвост незакрытых пунктов. После волны кодовая база `docs/arch.md` полностью соответствует, а сам документ честно описывает оставшиеся осознанные отступления.

В волну входит: имена классов по таблице «Имена ролей» (25); дерево каждого модуля без лишних и устаревших разделов, включая `Modules/Outbox/Infrastructure/Exception` (26); самодостаточность всех 9 модулей и причина для каждого оставшегося глобального элемента (27); инструменты (autoload, PHPStan, PHPUnit, Makefile, docker) без ручных исключений, `BearerSecurityConfig` в OpenAPI, починка `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` (28); автоматическая проверка архитектурных границ в обычном прогоне (29); обновлённый статус `docs/arch.md` со списком отступлений (30); и накопленный хвост: `tests/Support/Media/PersistsMedia.php`, статические методы `PostVisibilityPolicy`, устаревшие докблоки, `FindMediaUrlsQuery::$presignedTtlSeconds`, fallback в `OutboxRelay`/`OutboxQueueStatusInterceptor`, суппрессия PHPStan в `MediaMapper`, конфликт `RequireSingleRouteAccessAttributeRule` и `public-attribute.md`, судьба общих исключений `Shared/Domain/Exception/{NotFound,Forbidden,Validation,Authentication}Exception`, судьба пакета `gian-tiaga/spiral-outbox`.

В волну не входит: новая продуктовая функциональность, изменение схемы БД, изменение внешнего поведения — кроме двух согласованных исключений (спецификация OpenAPI начинает описывать требование Bearer-сессии; тест S3 перестаёт падать). Правки `vendor/gian-tiaga/*` не входят — эти пакеты правятся в своих репозиториях.

## Целевой алгоритм

Волна не меняет пользовательский сценарий — она меняет расположение, имена и защиту границ кода. Наблюдаемое отличие ограничено двумя пунктами: `public/openapi/openapi.yml` получает `securitySchemes`/`security`, и `S3MediaFileServiceTest` зеленеет. Остальное подтверждается структурными и архитектурными проверками, а не поведенческими тестами.

### Найденные факты, определяющие фазы

Аудит (проведён при подготовке плана, read-only) дал следующие проверенные факты, на которые опираются фазы ниже.

**Задача 25 — нарушения конвенции имён** (проверены все 13 семейств ролей по всем модулям и `Shared`; 11 семейств без единого нарушения):

| Файл | Текущее имя | Целевое имя |
|---|---|---|
| `app/src/Modules/Posts/Application/Data/PostRelatedIds.php` | `PostRelatedIds` | `PostRelatedIdsData` |
| `app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php` | `MediaAttachableResult` | `CheckMediaAttachableResult` |
| `app/src/Modules/Notifications/Application/Query/Notification/GetUnreadCount/UnreadCountResult.php` | `UnreadCountResult` | `GetUnreadCountResult` |

Прочие проверенные семейства (Domain Entity, Cycle Entity, Domain/Cycle Repository, Reader/Cycle Reader, DataCollection/PageData, Command/Query/Handler, Contract, Provider, Dto, Mapper, Columns, Typecast, Config) нарушений не имеют — переименований не требуют.

**Задача 26 — расхождения дерева модулей** (полное сравнение факта с целевым деревом по всем 9 модулям):

| Модуль | Лишний раздел | Содержимое | Решение и причина |
|---|---|---|---|
| Outbox | `Infrastructure/Exception` | `OutboxJobRegistryException`, `OutboxRelayStoppedException` | → `Application/Exception`: в целевом дереве нет исключений на уровне `Infrastructure`; оба — технические ошибки адаптеров, не доменные; в модуле уже есть аналогичные по роли в `Application/Exception` |
| Auth | `Infrastructure/Spiral/Hash` | `HmacSecretHasher` (реализация `SecretHasherContract`) | → новый раздел `Infrastructure/Spiral/Adapter` |
| Auth, Posts | `Infrastructure/Spiral/Translation` | `SpiralTranslator` (реализация собственного `TranslatorContract` каждого модуля; код идентичен, порт свой) | → `Infrastructure/Spiral/Adapter` |
| Notifications | `Infrastructure/Spiral/Registry` | `NotificationTypeRegistry` (реализация `NotificationTypeCatalogContract`) | остаётся, раздел `Registry` добавляется в дерево |
| Outbox | `Infrastructure/Spiral/Registry` | `OutboxJobRegistry` (реализация `OutboxJobRegistryContract`) | остаётся, раздел `Registry` |
| Outbox | `Infrastructure/Spiral/Queue` | `OutboxQueuePublisher`/`Serializer`/`StatusInterceptor`/`Headers` | остаётся, раздел `Queue` добавляется в дерево |
| Outbox | `Infrastructure/Relay` (верхнеуровневый, без Spiral-импортов) | `OutboxRelay`, `OutboxRelayWorker`, loop control, sleeper | остаётся — явно названная граница владельца очереди (Runtime: «очередь и гарантированная доставка — Outbox») |
| Outbox | `Infrastructure/Serializer` (верхнеуровневый) | `ValinorOutboxMessageSerializer` | остаётся, та же причина |
| Media | `Infrastructure/Ffmpeg`, `Infrastructure/Imagick` | адаптеры внешних библиотек обработки медиа | остаются — явно названные границы владельца файлов (Runtime: «файлы и S3/MinIO — Media») |
| System | `Infrastructure/Spiral/Http/Enum` | `HealthStatus` (enum формы ответа) | → `Http/Resource` |
| System | `Infrastructure/Spiral/Http/View` | `SwaggerView` (HTML-ответ Swagger UI) | → `Http/Response` (обёртка ответа — та же роль, что у HTTP Response) |
| Access | нет `Application`/`Public` | — | не расхождение дерева — разделы не создаются без кода; см. отступление задачи 30 (модуль не подключён к маршрутам) |

Дополнение дерева `docs/arch.md` (раздел «Структура», подпапки `Infrastructure/Spiral`): добавляются `Adapter/`, `Registry/`, `Queue/` — по смыслу, зафиксированному в таблице выше. Верхнеуровневый список `Infrastructure/{Cache,Client,Storage}` дополняется пояснением, что это представительный, не исчерпывающий список (принцип «каждая технология — явно названная граница» уже сформулирован в `docs/arch.md`, «Границы слоёв → Infrastructure»); текущие дополнительные явно названные границы перечисляются там же: `Media/Infrastructure/{Ffmpeg,Imagick}`, `Outbox/Infrastructure/{Relay,Serializer}`.

**Задача 27 — самодостаточность**: bootloader есть и зарегистрирован в `Shared/Infrastructure/Spiral/Kernel.php` у всех 9 модулей, порядок регистрации важен (Notifications зависит от реестра Job Outbox, Posts — от реестра видов уведомлений Notifications) и уже описан комментарием в Kernel. Утечек файлов модулей в `Shared`/`app/config` не найдено. Оставшиеся глобальные элементы и причины:

| Элемент | Причина остаться глобальным |
|---|---|
| `app/config/{cache,cycle,database,locale,migration,queue,scaffolder,session,storage,translator}.php` | общий runtime-состав Spiral/Cycle/RoadRunner без владельца-модуля |
| `app/config/queue.php` регистрирует `OutboxQueueStatusInterceptor` в `consume`-интерсепторах | не может переехать в bootloader модуля: `Spiral\Queue\Bootloader\QueueBootloader` необратимо забирает секцию `queue` раньше прикладных `boot()` (докблок `OutboxJobRegistry`) — принятое ограничение платформы |
| `app/locale/{en,ru}/shared.php` | переводы без владельца-модуля |
| `tests/` (62 файла: каркас PHPUnit/Spiral, `TestKernel`, `ApiErrorTestController`/`Filter`/`Bootloader`, `FakeStorage`, `ReplaysMigration`, `RecordingOutboxEventStore`, `CleansOutboxEvents`, `CqrsContainerTest`, тесты `Shared`) | обоснованы построчно в `docs/artifacts/executions/2026-09-17_11-30_volna-h-testy-vnutri-modulej.md` («Что осталось в корневом `tests/` и почему»); волна I трогает только `PersistsMedia.php` (ниже) |
| `Shared/Infrastructure/Spiral/Kernel` | явно перечислен в `docs/arch.md` как компонент без модуля-владельца |

`tests/Support/Media/PersistsMedia.php`: реальный `use`-импорт трейта сегодня есть только у 5 тестов `Notifications` (подтверждено приёмкой волны H); упоминания в `User`/`Posts` — только в докблоках без импорта. Переносится в `Notifications/Tests` (владелец подтверждён фактическим использованием), докблоки `User`/`Posts` перестают на него ссылаться.

**Задача 28 — тест S3**: код (`S3MediaFileService::objectKey()`/`publicUrl()`) корректен. Причина — конфигурация тестового окружения: `.env` заранее устанавливает три `MEDIA_*_STORAGE_PREFIX` в пустую строку; `test-runner` в `docker-compose.dev.yml` переопределяет `DB_DATABASE`/`STORAGE_DEFAULT`/`S3_BUCKET`, но не их; `phpunit.xml` задаёт их в `<env>` без `force="true"`, поэтому PHPUnit не перекрывает уже установленную переменную (`getenv()` возвращает `""`, не `false`). Тест и код не меняются; чинится `docker-compose.dev.yml`.

**Задача 28 — OpenAPI и `BearerSecurityConfig`**: класс есть в `gian-tiaga/spiral-openapi`, точка использования — необязательный параметр `bearerSecurity` в `OpenApiGeneratorConfig`, сегодня не передаётся. Волна C сознательно не включала его ради побайтовой неизменности спецификации на момент той волны (журнал волны C: «`BearerSecurityConfig`... включать нельзя — он добавит в операции ключ `security` и нарушит требование побайтовой неизменности»); эта причина к волне I больше не действует.

**Задача 29 — инструмент проверки границ**: выбран `deptrac/deptrac` (стабильная `4.7.2`, PHP `^8.2`, проект на `8.5`) — решение зафиксировано в research-карте (закрытая развилка 8): декларативное описание слоёв ложится напрямую на таблицу `docs/arch.md` «Направления зависимостей»; альтернатива (собственные PHPStan-правила поверх `gian-tiaga/phpstan-strict-rules`) отклонена как более трудоёмкая при том же результате, остаётся запасной только если deptrac не выразит правило «сосед — только через `Public`». Сейчас в проекте нет инструмента проверки границ вовсе.

**Хвост — уже разрешённые пункты, не требующие кода**:
- `RequireSingleRouteAccessAttributeRule` vs `public-attribute.md`: конфликта нет. `phpstan.neon` уже конфигурирует `routeAccessAttributeClasses` только взаимоисключающей парой (`PublicRoute`, `AuthenticatedRoute`) и комментарием прямо объясняет, что накопительное требование (`RequiresPermission`) в этот список специально не входит — правило требует «ровно одно» только среди взаимоисключающих деклараций. Карточка и правило уже сведены; фиксируется в журнале без изменения кода.
- Докблоки, ссылающиеся на удалённые классы вида `MediaView`: полный список имён, удалённых волнами B и F (`AuthorView`, `CommentView*`, `PostView*`, `TagView`, `NotificationView*`, `MediaConversionView`, `MediaOriginalView`, `MediaView`, `PostMediaItemView`, `PostMediaItemResource`), проверен по всей кодовой базе — ни одно упоминание не найдено. Пункт уже закрыт, фиксируется в журнале.
- `FindMediaUrlsQuery::$presignedTtlSeconds`: параметр используется внутри `FindMediaUrlsHandler` (передаётся в `MediaUrlServiceContract::getUrls()`), единственный вызывающий (`MediaProvider`) сегодня передаёт только `null`. Это не мёртвый код, а часть контракта Query, которую пока использует один сценарий — решение: оставить без изменений, `rules.md` запрещает удалять код, не ставший неиспользуемым из-за текущей правки.
- `Shared/Domain/Exception/{NotFoundException,ForbiddenException,ValidationException,AuthenticationException}`: подтверждено — ни один класс `app/src` их не выбрасывает (каждый модуль завёл свой доменный `{Name}NotFoundException extends DomainTranslatableException`). Решение: оставить как переиспользуемые примитивы без владельца-модуля (легитимно для `Shared/Domain` по `docs/arch.md`), их покрывает `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php` и тестовый `tests/App/Modules/System/Http/ApiErrorTestController.php`, который проверяет общий механизм перевода ошибок `spiral-api-errors` независимо от бизнес-модуля — удаление потребовало бы либо дублирования этой сквозной проверки на одном из бизнес-исключений, либо потери проверки. Фиксируется как принятое решение в `docs/arch.md`, кода не меняет.
- `@phpstan-ignore varTag.nativeType` ×3 в `MediaMapper` (image/video/audio conversions): точечное, с причиной в комментарии рядом (native-тип `HasMany`-свойства `CycleMediaEntity` — доменная коллекция по дизайну волны E, реальное eager-load содержимое на момент чтения — Cycle Entity). Уже соответствует `docs/rules.md` («исключение точечное, с причиной, минимальный путь») — решение: оставить, кода не менять.

## Контракты реализации

### Данные и БД

Не затрагивается. Миграций волна не создаёт и не меняет.

### API и внешние контракты

`public/openapi/openapi.yml`: добавляется `securitySchemes` с одной bearer-схемой и `security` на операциях маршрутов, объявленных `App\Modules\Auth\Public\Attribute\AuthenticatedRoute` (защищённые) — отсутствует у операций `PublicRoute`. Формируется через `BearerSecurityConfig(schemeName: ..., publicAccessAttributeClasses: [PublicRoute::class], protectedAccessAttributeClasses: [AuthenticatedRoute::class])`, передаваемый в `OpenApiConfig::toGeneratorConfig()`. Пути запросов/ответов, схемы данных и статусы не меняются — единственное отличие сгенерированного файла: раздел `securitySchemes` и поле `security` на операциях.

`docker/docker-compose.dev.yml`, сервис `test-runner`, блок `environment`: добавляются `MEDIA_UPLOAD_STORAGE_PREFIX: test`, `MEDIA_PRIVATE_STORAGE_PREFIX: test`, `MEDIA_PUBLIC_STORAGE_PREFIX: test` — по аналогии с уже существующими переопределениями `DB_DATABASE`/`STORAGE_DEFAULT`/`S3_BUCKET` в том же блоке.

`composer.json`: зависимость `gian-tiaga/spiral-outbox` удаляется (ни одной ссылки на `GianTiaga\SpiralOutbox` в `app/src`/`tests`, не подключена ни в один bootloader — полностью дублирует собственный модуль `Outbox`, правка чужого пакета не требуется). Добавляется dev-зависимость `deptrac/deptrac` версии `^4.7`.

Новый файл `deptrac.yaml` в корне проекта: слои — `Domain`, `Public`, `Application`, `Infrastructure`/`InfrastructureSpiral` на каждый из 9 модулей, плюс `SharedDomain`, `SharedApplication`, `SharedInfrastructure`, `Kernel`, и служебные слои `SpiralFramework` (namespace `Spiral\`), `CycleOrm` (namespace `Cycle\`). Ruleset — один в один `docs/arch.md`, раздел «Направления зависимостей»: `{Module}Domain` → PHP, свой `Domain`, `SharedDomain`; `{Module}Public` → PHP, свой `Public`, `Public` других модулей, `SharedDomain`; `{Module}Application` → свой `Domain`, свои `Application/Contract`, `Public` других модулей (не их `Domain`/`Application`/`Infrastructure`); `{Module}Infrastructure`/`InfrastructureSpiral` → свой `Domain`, `Application`, `Public`, `SharedInfrastructure`, `SpiralFramework`/`CycleOrm`; `SharedInfrastructure` не зависит ни от одного бизнес-модуля; `Kernel` → bootloader-ы модулей. `Domain`, `Application`, `Public` каждого модуля не имеют разрешённой зависимости на `SpiralFramework`/`CycleOrm` — это и есть запрет фреймворка и Cycle в этих слоях. Межмодульная зависимость бизнес-слоёв разрешена только на `{OtherModule}Public`, не на его `Domain`/`Application`/`Infrastructure` — это проверяет и «сосед только через `Public`», и «нет ORM-связей через границу модуля» (Cycle relation ссылается на целевой класс через `use`, deptrac видит его как обычную зависимость).

`composer.json`, секция `scripts`: добавляется скрипт `deptrac` (`deptrac analyse --fail-on-uncovered=0` или эквивалент, дающий ненулевой код возврата при любом нарушении), включается в массив `qa` рядом с `@cs`, `@phpstan`, `@test-coverage`, чтобы `make qa` проверял границы автоматически.

## Фазы выполнения

### 1. Дерево модулей и самодостаточность

Цель: кодовая база физически соответствует целевому дереву модулей, включая согласованные дополнения самого дерева, а тестовая инфраструктура лежит у подтверждённого владельца.

Что сделать: каждая строка «Решение» таблицы задачи 26 выполняется как есть — переносы (`Outbox/Infrastructure/Exception`, `Auth`/`Posts` Hash и Translation, System Http/Enum и Http/View) и дополнения дерева (новые подпапки, пояснение о представительности списка `Infrastructure/{Cache,Client,Storage}`) вносятся в `docs/arch.md` и в код одновременно, с обновлением всех `use`-импортов на перенесённые классы. `tests/Support/Media/PersistsMedia.php` переносится в `app/src/Modules/Notifications/Tests` (namespace и 5 site импортов обновляются), докблоки `User`/`Posts`, ссылающиеся на старый путь, правятся.

Результат: `find app/src/Modules/{Module} -type d` для всех 9 модулей не содержит разделов вне обновлённого целевого дерева; `docs/arch.md` отражает согласованные дополнения; `PersistsMedia` лежит у фактического потребителя.

Проверка:
- `make test-unit` и `make phpstan` зелёные;
- `grep -rl "namespace App\\\\Modules\\\\Outbox\\\\Infrastructure\\\\Exception" app/src` пуст;
- `grep -rln "Support\\\\Media\\\\PersistsMedia\|tests/Support/Media" app/src/Modules/{User,Posts}` пуст.

### 2. Имена классов

Цель: три класса, расходящихся с таблицей «Имена ролей», переименованы; остальные 11 проверенных семейств подтверждены соответствующими — переименований не требуют.

Что сделать: `PostRelatedIds` → `PostRelatedIdsData` (`app/src/Modules/Posts/Application/Data`), `MediaAttachableResult` → `CheckMediaAttachableResult` (`app/src/Modules/Media/Application/Query/CheckMediaAttachable`), `UnreadCountResult` → `GetUnreadCountResult` (`app/src/Modules/Notifications/Application/Query/Notification/GetUnreadCount`). Каждое переименование включает файл, класс и все использующие его сайты (handler, тесты, докблоки).

Результат: три класса названы по конвенции, использующий код и тесты ссылаются на новые имена.

Проверка:
- `make test-unit` и `make phpstan` зелёные;
- `grep -rn "PostRelatedIds\b\|MediaAttachableResult\|UnreadCountResult\b" app/src` не находит старых имён вне git-истории.

### 3. Хвост: домен, сценарии, зависимости

Цель: закрыты доменные и сценарные пункты накопленного хвоста, кроме уже разрешённых без кода (зафиксированы в «Целевом алгоритме» и переносятся в журнал этой фазы без правок).

Что сделать: `PostVisibilityPolicy` становится классом с инстанс-методами `isVisibleTo()`/`isActionable()` вместо статических — по образцу `docs/references/domain-service.md`; вызывающий сценарий получает экземпляр через конструктор. В `OutboxRelay::publish()` (обе ветки) и в `OutboxQueueStatusInterceptor` (основной поток и `recordJobFailure()`) паттерн `$this->storedOutboxEventRepository->findById(...) ?? $storedOutboxEvent` заменяется: если `findById()` вернул `null` (строка исчезла между чтениями), запись пропускается с предупреждением в лог вместо восстановления устаревшего снимка и попытки его сохранить. `composer.json` лишается зависимости `gian-tiaga/spiral-outbox` (не используется, дублирует `Outbox`, сам пакет не редактируется).

Результат: `PostVisibilityPolicy` — обычный класс с зависимостями через конструктор (их нет, как и раньше); relay и interceptor больше не перезаписывают состояние по устаревшему снимку при исчезновении события; неиспользуемая зависимость выведена из `composer.json`/`composer.lock`.

Проверка:
- `make test-unit` и `make phpstan` зелёные;
- `grep -rn "public static function isVisibleTo\|public static function isActionable" app/src/Modules/Posts` пуст;
- `grep -n "spiral-outbox" composer.json composer.lock` пуст;
- новый тест на пропуск записи при исчезновении строки в `OutboxRelay`/`OutboxQueueStatusInterceptor` (positive/negative по `docs/rules.md`: воспроизводит сценарий «строка исчезла между чтениями», проверяет отсутствие записи устаревшего снимка и наличие предупреждения в логе).

### 4. OpenAPI, Bearer-безопасность и тест S3

Цель: спецификация API честно описывает требование Bearer-сессии; единственный падающий тест проекта зелёный без ослабления утверждений.

Что сделать: `OpenApiConfig::toGeneratorConfig()` передаёт `bearerSecurity` через `BearerSecurityConfig` с `publicAccessAttributeClasses: [PublicRoute::class]` и `protectedAccessAttributeClasses: [AuthenticatedRoute::class]`. Спецификация перегенерируется командой `openapi:generate`, закоммиченный `public/openapi/openapi.yml` обновляется результатом, тест генерации (`OpenApiGenerateCommandTest` и/или отдельная проверка контрольной суммы, если она есть) обновляется на новое ожидаемое содержимое/сумму — как требует `docs/rules.md` при изменении публичного HTTP API. `docker/docker-compose.dev.yml`, сервис `test-runner`: три переменные `MEDIA_{UPLOAD,PRIVATE,PUBLIC}_STORAGE_PREFIX` получают значение `test` в блоке `environment`.

Результат: сгенерированный и закоммиченный `openapi.yml` содержат `securitySchemes`/`security`; `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` проходит без изменения теста и кода `S3MediaFileService`.

Проверка:
- `make test-unit` и `make phpstan` зелёные;
- `php app.php openapi:generate` и `git diff --stat public/openapi/openapi.yml` — после коммита фазы diff пуст (файл уже актуален);
- `grep -n "securitySchemes" public/openapi/openapi.yml` находит раздел;
- точечный прогон `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` в Docker зелёный.

### 5. Автоматическая защита границ (deptrac)

Цель: направления зависимостей между слоями и модулями, запрет фреймворка и Cycle в `Domain`/`Application`/`Public`, обращение к соседу только через его `Public`, отсутствие импорта бизнес-модулей в `Shared` — проверяются автоматически и падают при нарушении, проверка встроена в обычный прогон `make qa`.

Что сделать: устанавливается `deptrac/deptrac`, создаётся `deptrac.yaml` по описанию из «Контрактов реализации». `composer.json` получает скрипт `deptrac`, включённый в составной `qa`-скрипт; `Makefile`/`docker/test/run-qa.sh` прогоняют его вместе с `cs`/`phpstan`/`test-coverage`. Первый прогон разбирает и закрывает все найденные реальные нарушения (ожидаются единичные) без ослабления правил — никаких deptrac-баселайнов, исключающих реальный код.

Результат: `make qa` (и отдельно доступный шаг проверки границ) падает при любом нарушении направления зависимостей.

Проверка:
- `make test-unit` и `make phpstan` зелёные;
- чистый прогон deptrac зелёный (`vendor/bin/deptrac analyse` через Docker, без нарушений);
- эксперимент на каждый класс границ (журнал фазы, откат сразу после фиксации падения): (а) `use Spiral\...` в файле `Domain` — падает; (б) `use Cycle\...` в файле `Application` — падает; (в) прямой `use` класса `Domain` соседнего модуля из `Application`, минуя его `Public` — падает; (г) ORM-связь с `target` на `Cycle{Name}Entity` соседнего модуля — падает (цель импортируется через `use`); (д) `use App\Modules\{AnyBusiness}\...` внутри `Shared` — падает.

### 6. Статус архитектуры

Цель: `docs/arch.md` описывает целевое состояние как достигнутое и перечисляет все осознанные отступления с причиной.

Что сделать: `docs/arch.md` дополняется списком отступлений с причиной по каждому: `UserId` в `Shared/Domain` вместо User (общий примитив без поведения, ~97 файлов семи модулей); `Access` не подключён ни к одному маршруту (в продукте нет маршрутов служебных действий под его права; bootloader зарегистрирован, модуль готов); миграция `20260617.160942_0_create_posts_domain_tables.php` (Posts) создаёт таблицу `tags` (применённую миграцию нельзя редактировать, последующие изменения `tags` уже в модуле Tags); пары `add()`/`save()` в пяти доменных Repository (управление границей ровно одного прогона `EntityManager` на сценарий с несколькими корнями агрегатов — семантика одинакова и задокументирована во всех пяти); `DomainTranslatableException` импортирует `GianTiaga\SpiralApiErrors` (нейтральный контракт своего composer-пакета, не фреймворк); четыре общих исключения `Shared/Domain/Exception` без активного потребителя в `app/src` (переиспользуемые примитивы без владельца, покрыты сквозной проверкой). Раздел «Структура» получает дополнения из фазы 1.

Результат: `docs/arch.md` не содержит утверждений, расходящихся с фактическим состоянием кода после фаз 1–5; каждое расхождение либо устранено, либо явно названо с причиной.

Проверка:
- `make test-unit` и `make phpstan` зелёные (документация не влияет на код, но фаза не должна оставить рассинхрон между планом и журналом);
- ручная сверка: для каждого пункта таблицы отступлений в `docs/arch.md` есть проверяемое фактическое подтверждение (путь к файлу/миграции).

### 7. Приёмка волны

Цель: подтверждён зелёный `make qa` без исключений и выполнены количественные критерии волны.

Что сделать: полный `make qa` в Docker; при красном — цикл диагностики и точечного исправления причины (не ослабления проверки) до зелёного. Проверяются числа: тестов не меньше 1549, покрытие 100%, PHPStan level max чист, php-cs-fixer чист, `route:list` — 33 маршрута, `openapi:generate` не даёт diff с закоммиченным файлом. Эксперимент фазы 5 (доказательство падения deptrac на каждом классе нарушения) переносится в акт приёмки как готовое доказательство, либо повторяется, если код с фазы 5 успел измениться. Осиротевшие контейнеры `test-runner-run-*` останавливаются и удаляются.

Результат: `make qa` зелёный без единого падения; количественные критерии волны выполнены и подтверждены числами в журнале.

Проверка:
- `make qa` — 0 failures, 0 errors;
- `docker ps -a --filter name=test-runner-run- ` пуст после уборки.

## Тесты

`test_strategy: after_each_phase`, адаптированная под явное требование волны: каждая фаза заканчивается быстрым `make test-unit` и `make phpstan` (не полный `make qa`) — они дешёвы и ловят коллизии имён, синтаксис и типы. Полный `make qa` прогоняется один раз в фазе 7, с циклами исправления до зелёного.

Новые тесты — только в фазе 3 (см. её «Проверка»): unit `PostVisibilityPolicy` на инстанс (положительный/отрицательный по каждому статусу поста и по `isActionable()`) и интеграционный тест пропуска записи при исчезновении строки outbox-события — отдельно для `OutboxRelay::publish()` и `OutboxQueueStatusInterceptor`. Остальные фазы проверяются командами, перечисленными в их разделах «Проверка» — не дублируются здесь.

## Логирование

`standard`: warning при пропуске обработки события outbox из-за исчезнувшей строки (фаза 3) — по образцу уже принятого в модуле формата (`message`, `context` с `outboxId`/`outboxType` где применимо). Секреты, токены и содержимое приватных файлов в логах не появляются (без изменений относительно текущего кода).

## Документация и эксплуатация

`docs/arch.md` обновляется дважды по ходу волны: в фазе 1 (дерево) и в фазе 6 (статус и отступления) — оба изменения кладутся в один документ последовательно, не создавая рассинхрон между фазами. Журнал волны (`docs/artifacts/executions/...`) фиксирует решения по каждому пункту хвоста, включая уже разрешённые без кода (карточка `public-attribute.md` vs правило PHPStan, докблоки `MediaView`), — чтобы приёмка не переоткрывала их.

## Принятые решения

Обоснования и источники — в разделе «Целевой алгоритм» (подраздел «Хвост — уже разрешённые пункты» и таблицы задач 25/26/27/28/29). Здесь — только сами решения, `autonomous`, без вариантов:

- Инструмент проверки границ — `deptrac/deptrac ^4.7`.
- `UserId` остаётся в `Shared/Domain`, документируется как отступление в фазе 6, не переносится.
- `gian-tiaga/spiral-outbox` удаляется из `composer.json`, сам пакет не редактируется.
- `RequireSingleRouteAccessAttributeRule` и `public-attribute.md` не меняются — уже сведены конфигурацией `phpstan.neon`.
- Четыре общих исключения `Shared/Domain/Exception` остаются без изменений.
- `FindMediaUrlsQuery::$presignedTtlSeconds` не удаляется.
- Докблоки на удалённые волнами B/F классы `*View` не найдены — пункт закрыт без правок.
- Суппрессии `varTag.nativeType` в `MediaMapper` остаются — уже точечные и с причиной.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-17_15-05_volna-i-imena-derevo-instrumenty-granicy-status.md`. Режим — `subagents`. Статус и таблица фаз ведутся в журнале, не здесь.
