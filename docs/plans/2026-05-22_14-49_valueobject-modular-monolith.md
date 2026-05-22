---
title: "Перенос ValueObject на чистую доменную модель и модульный монолит"
date: "2026-05-22 14:49"
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача

Перевести текущий код на модульный монолит и изменить работу `ValueObject` так,
чтобы доменные объекты не зависели от Cycle ORM и инфраструктурных интерфейсов.

Готовый результат:

- бизнес-код `Media` лежит в `App\Modules\Media`;
- общая инфраструктура лежит в `App\Shared\Infrastructure`;
- общие доменные исключения лежат в `App\Shared\Domain\Exception`;
- `ValueObject` не реализуют `Castable` / `JsonCastable` и не содержат методов
  `fromDatabase()` / `toDatabase()`;
- восстановление и запись `ValueObject` в БД выполняет инфраструктурный
  typecast-слой;
- публичные HTTP-маршруты, console-команды, Temporal workflow, таблицы и API
  остаются совместимыми;
- `make test` и `make phpstan` проходят.

## Контекст

Проект уже зафиксирован в `docs/arch.md` как модульный монолит. Целевая
структура модуля:

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

Сейчас код ещё лежит в старой структуре:

```text
app/src/Domain
app/src/Repository
app/src/Endpoint
app/src/Infrastructure
```

В текущем коде уже есть медиа-домен:

- сущности `Media`, `MediaImageConversion`, `MediaVideoConversion`,
  `MediaMultipartUpload`;
- enum-ы медиа;
- коллекции медиа;
- `ValueObject` медиа;
- репозитории медиа;
- миграция `20260521.184100_0_create_media_domain_tables.php`;
- тесты для медиа-домена, репозиториев и `ValueObjectCast`.

Проблема текущей реализации: доменные `ValueObject` импортируют
`App\Infrastructure\Cycle\Castable` или `JsonCastable`. Это нарушает новое
правило: `Domain` не зависит от инфраструктуры.

Ещё один старый корневой доменный блок - `app/src/Domain/Exception`. Эти
исключения используются не только медиа-кодом, поэтому они не относятся к модулю
`Media` и должны перейти в общий доменный слой.

`composer.json` уже содержит PSR-4 autoload:

```text
App\ -> app/src
```

Значит отдельная настройка autoload для `App\Modules` и `App\Shared` не нужна.

## Принятые решения

- Архитектурное решение подтверждено пользователем: использовать вариант 2.
  Медиа переносится в `app/src/Modules/Media`, общая инфраструктура переносится
  в `app/src/Shared/Infrastructure`.
- Общие доменные исключения переносятся в `app/src/Shared/Domain/Exception`.
  Это часть выбранной пользователем структуры `Shared`: исключения используются
  разными модулями и не принадлежат только `Media`.
- Общие доменные трейты переносятся в `app/src/Shared/Domain/Trait`. Текущий
  `HasTimestamps` не относится только к `Media`.
- Namespace целевой структуры выводится из PSR-4 `App\ -> app/src`:
  `App\Modules\Media\...` и `App\Shared\Infrastructure\...`.
- Контракты технических сервисов модуля лежат в
  `Modules/{Module}/Application/Contract` и имеют суффикс `Contract`.
- `Application` зависит от своего `Domain`, своего `Repository` и своих
  `Contract`. `Infrastructure` реализует эти контракты.
- Репозитории лежат отдельным слоем модуля: `Modules/{Module}/Repository`.
- Контракты для репозиториев не создаются автоматически.
- Технические сервисы для файлов медиа называются явно: контракт
  `MediaFileServiceContract` лежит в `Application/Contract`, S3-реализация
  называется `S3MediaFileService` и лежит в `Infrastructure/FileService`.
- Текущие технические endpoint-ы без отдельной бизнес-области переносятся в
  модуль `System`: health endpoint, Swagger controller, OpenAPI console-команды
  и Temporal ping workflow.
- Таблицы БД, имена таблиц, имена колонок, route path, route name, console command
  name и Temporal workflow name не меняются.
- Сущности Cycle остаются доменными классами модуля `Media`, но их Cycle
  attributes используют инфраструктурные typecast-классы из `Shared` или
  `Media/Infrastructure`.
- `ValueObjectCast` переносится в `App\Shared\Infrastructure\Cycle` и работает
  без доменных интерфейсов.
- Для простых `ValueObject` используется общий `ValueObjectCast` по соглашению:
  восстановление через публичную фабрику `fromString()` или `fromInt()`, запись
  через `value()`.
- Для сложных случаев используются отдельные typecast-классы в
  `Modules/Media/Infrastructure/Cycle`: nullable date, nullable string и JSON
  collection.
- Отдельные typecast-классы реализуют инфраструктурный контракт
  `App\Shared\Infrastructure\Cycle\ColumnValueTypecast`. Доменные `ValueObject`
  этот контракт не реализуют.
- Тесты пишутся и запускаются после каждой фазы, потому что в настройках
  `test_strategy: after_each_phase`.
- Логирование остаётся подробным только там, где уже есть технический debug,
  например в OpenAPI generation. Новые runtime-логи для переноса namespace не
  добавляются.

## Целевой алгоритм

1. Composer загружает классы из `app/src` как namespace `App`.
2. Kernel стартует из `App\Shared\Infrastructure\Framework\Kernel`.
3. Bootloader-ы из `Shared\Infrastructure\Framework\Bootloader` настраивают
   конфиги, маршруты, OpenAPI, обработку ошибок и остальные общие сервисы.
4. Общие исключения доступны из `App\Shared\Domain\Exception`.
5. Spiral tokenizer находит attributes в `App\Modules`.
6. HTTP-запрос `/api/v1/health` попадает в
   `Modules/System/Presentation/Http/HealthController` и возвращает тот же ответ,
   что сейчас.
7. Console-команды OpenAPI остаются с теми же именами, но их классы живут в
   `Modules/System/Presentation/Console`.
8. `app/config/openapi.php` указывает на новый путь и namespace HTTP-слоя:
   `app/src/Modules/System/Presentation/Http` и
   `App\Modules\System\Presentation\Http`.
9. Cycle ORM читает entity из `Modules/Media/Domain/Entity`.
10. Сценарии модуля обращаются к БД через свой слой
   `Modules/Media/Repository`.
11. Сценарии модуля обращаются к внешним техническим сервисам через контракты из
   `Modules/Media/Application/Contract`.
12. Реализации технических контрактов находятся в
   `Modules/Media/Infrastructure`, например файловые сервисы в `FileService`.
13. Для простого поля с `#[Column(typecast: MediaPath::class)]` общий
   `ValueObjectCast` восстанавливает объект через `MediaPath::fromString()` и
   записывает значение через `MediaPath::value()`.
14. Для сложного поля с отдельным typecast-классом, например multipart parts,
   `ValueObjectCast` вызывает `castDatabaseValue()` и `uncastValue()`.
15. `ValueObject` остаётся чистым доменным объектом: он валидирует значения,
    сравнивает значения и отдаёт публичное значение, но не знает про БД.
16. При ошибке восстановления поля инфраструктурный typecast-слой бросает
    `TypecastException` с именем поля. Сообщение не содержит секретов и
    персональных данных.

## Контракты реализации

### Данные и БД

Схема БД не меняется.

Сохраняются существующие таблицы:

- `media`;
- `media_image_conversions`;
- `media_video_conversions`;
- `media_multipart_uploads`.

Сохраняются:

- все имена колонок;
- типы колонок;
- обязательность и nullable;
- индексы;
- уникальные ограничения;
- внешние ключи;
- миграция `20260521.184100_0_create_media_domain_tables.php`.

Backfill не нужен, потому что формат данных в таблицах не меняется.

Rollback на уровне данных не нужен, потому что миграции БД не меняются.

После переноса namespace нужно пересобрать Cycle schema cache штатными проверками
через Docker.

### API и внешние контракты

HTTP API не меняется.

Сохраняется:

- `GET /api/v1/health`;
- route name `api.v1.health`;
- response wrapper `DataResponse`;
- структура `HealthResource`;
- Swagger UI route;
- route `/api/docs/openapi.yml`;
- console command `openapi:generate`;
- console command публикации OpenAPI assets;
- Temporal workflow `ping`.

Новые публичные маршруты не добавляются.

Ошибки и status code не меняются.

## Фазы выполнения

### 1. Перенести общий код в Shared

Цель: убрать старые корневые общие слои как целевое место для кода и
подготовить основу для модулей.

Что сделать:

- Перенести `app/src/Infrastructure/Configuration` в
  `app/src/Shared/Infrastructure/Configuration`.
- Перенести `app/src/Infrastructure/Framework` в
  `app/src/Shared/Infrastructure/Framework`.
- Перенести `app/src/Infrastructure/Cache` в
  `app/src/Shared/Infrastructure/Cache`.
- Перенести `app/src/Infrastructure/Cycle` в
  `app/src/Shared/Infrastructure/Cycle`.
- Перенести `app/src/Domain/Exception` в
  `app/src/Shared/Domain/Exception`.
- Перенести `app/src/Domain/Trait` в `app/src/Shared/Domain/Trait`.
- Обновить namespace и imports с `App\Infrastructure\...` на
  `App\Shared\Infrastructure\...`.
- Обновить namespace и imports с `App\Domain\Exception\...` на
  `App\Shared\Domain\Exception\...`.
- Обновить namespace и imports с `App\Domain\Trait\...` на
  `App\Shared\Domain\Trait\...`.
- Обновить `app.php`, чтобы он использовал новый namespace `Kernel` и
  `DirectoryAlias`.
- Обновить `app/config/cache.php`, потому что там есть прямой import
  `RedisCacheStorage`.
- Обновить `ConfigBootloader`: путь сканирования typed config должен указывать
  на `app/src/Shared/Infrastructure/Configuration`, namespace prefix должен быть
  `App\Shared\Infrastructure\Configuration\`.
- Обновить тесты конфигурации и инфраструктуры под новый namespace.
- Обновить тесты API-ошибок под `App\Shared\Domain\Exception`.
- Удалить старые пустые директории, которые останутся после переноса.

Результат: приложение стартует с общим кодом из `Shared`, а старые namespace
`App\Infrastructure` и `App\Domain\Exception` больше не используются в коде
приложения.

Сценарии тестирования:

- typed config продолжает маппиться через контейнер;
- `Kernel` создаётся из нового namespace;
- `ValueObjectCast` доступен в новом namespace;
- общие исключения отдаются API error layer как раньше;
- `HasTimestamps` доступен из `App\Shared\Domain\Trait`;
- старые imports `App\Infrastructure\...` не остаются в `app/src` и `tests`.
- старые imports `App\Domain\Exception\...` не остаются в `app/src` и `tests`.
- старые imports `App\Domain\Trait\...` не остаются в `app/src` и `tests`.

Проверка:

- Запустить `make test`.
- Запустить `make phpstan`.
- Дополнительно проверить поиском:

```text
rg "App\\Infrastructure|App\\Domain\\Exception|App\\Domain\\Trait" app.php app/config app/src tests docs
```

### 2. Перенести код в модули

Цель: привести текущую структуру приложения к модульному монолиту.

Что сделать:

- Создать `app/src/Modules/Media`.
- Перенести медиа-домен:

```text
app/src/Domain/Entity      -> app/src/Modules/Media/Domain/Entity
app/src/Domain/ValueObject -> app/src/Modules/Media/Domain/ValueObject
app/src/Domain/Enum        -> app/src/Modules/Media/Domain/Enum
app/src/Domain/Collection  -> app/src/Modules/Media/Domain/Collection
```

- Перенести медиа-репозитории:

```text
app/src/Repository/Media*Repository.php
  -> app/src/Modules/Media/Repository/
```

- Обновить namespace медиа-кода на `App\Modules\Media\...`.
- Обновить `#[Entity(repository: ...)]` у медиа-сущностей на новые классы
  репозиториев в `Modules/Media/Repository`.
- Создать `app/src/Modules/Media/Application/Contract` для технических сервисов,
  которым нужны контракты.
- Зафиксировать направление для будущих сервисов файлов медиа:

```text
Application/Contract/MediaFileServiceContract.php
Infrastructure/FileService/S3MediaFileService.php
```

  Этот план не добавляет файловый сервис, если его ещё нет в коде. Он фиксирует
  место и название для будущей реализации.
- Создать `app/src/Modules/System`.
- Перенести технические endpoint-ы:

```text
Endpoint/Api/V1/Controller/HealthController
Endpoint/Api/V1/Enum/HealthStatus
Endpoint/Api/V1/Resource/AbstractResource
Endpoint/Api/V1/Resource/HealthResource
Endpoint/Api/V1/Controller/SwaggerController
Endpoint/Api/V1/View/SwaggerView
Endpoint/Console/OpenApi*
Endpoint/Temporal/Ping
```

  в `Modules/System/Presentation`.

- Оставить внешние route path, route name, console command name и Temporal
  workflow name без изменений.
- Обновить `app/config/openapi.php`:
  - `sourcePath` должен указывать на новый путь HTTP-слоя `System`;
  - `apiNamespace` должен указывать на новый namespace HTTP-слоя `System`;
  - `routePrefix`, `outputFile`, `title`, `version` и debug-настройки остаются
    прежними.
- Обновить imports в bootloader-ах, тестах, resources и docs examples.
- Перенести тесты в зеркальную структуру:

```text
tests/Unit/Modules/Media/Domain
tests/Feature/Modules/Media/Repository
tests/Unit/Modules/System
tests/Feature/Modules/System
```

- Обновить тестовую инфраструктуру `tests/App`:
  - тестовый routes bootloader;
  - тестовый API error controller;
  - тестовый filter;
  - `TestKernel`.
- Обновить старые feature-тесты из `tests/Feature/Endpoint` под новый namespace
  или перенести их в `tests/Feature/Modules/System`.
- Добавить проверку Temporal `Ping`: class instantiation и `handle()` возвращает
  `pong`, чтобы перенос workflow не остался непроверенным.
- Удалить старые пустые директории `Domain`, `Repository`, `Endpoint`.

Результат: текущий код приложения живёт либо в `Modules`, либо в `Shared`.

Сценарии тестирования:

- медиа-сущности создаются и меняют состояние как раньше;
- медиа-репозитории сохраняют и читают данные как раньше;
- `Application` модуля использует свои репозитории напрямую, без обязательных
  контрактов;
- health endpoint возвращает прежний response;
- OpenAPI generation работает с новыми namespace;
- `openapi:generate` действительно включает `/health` из нового `sourcePath`, а
  не использует старый путь или заранее готовый YAML;
- Temporal ping workflow остаётся доступен;
- в `app/src` не остаются старые корневые директории `Domain`, `Repository`,
  `Endpoint`, `Infrastructure`.

Проверка:

- Запустить `make test`.
- Запустить `make phpstan`.
- Дополнительно проверить поиском:

```text
rg "namespace App\\Domain|namespace App\\Repository|namespace App\\Endpoint|namespace App\\Infrastructure" app/src
rg "use App\\Domain|use App\\Repository|use App\\Endpoint|use App\\Infrastructure" app.php app/config app/src tests
```

### 3. Сделать ValueObject независимыми от Cycle ORM

Цель: выполнить новое правило из `docs/rules.md`: доменные `ValueObject` не знают
про Cycle ORM и инфраструктурные интерфейсы.

Что сделать:

- Удалить интерфейсы `Castable` и `JsonCastable` из доменного использования.
- Удалить `implements Castable` и `implements JsonCastable` из всех `ValueObject`
  и доменных коллекций.
- Удалить imports `App\Shared\Infrastructure\Cycle\Castable` и
  `App\Shared\Infrastructure\Cycle\JsonCastable` из `Modules/Media/Domain`.
- Удалить методы `fromDatabase()` и `toDatabase()` из доменных `ValueObject`.
- Для простых строковых `ValueObject` оставить или добавить:

```text
fromString(string): self
value(): string
equals(...)
__toString()
jsonSerialize()
```

- Для `AbstractUuidV7Id` добавить `value(): string` и использовать его в
  репозиториях вместо `toDatabase()`.
- Для `AbstractIntegerValue` оставить `fromInt()` и `value()`.
- Для `MediaExpiration` оставить доменные фабрики `permanent()` и
  `temporaryUntil()`, добавить явный метод `value(): ?DateTimeImmutable`.
- Для `MediaProcessingError` оставить доменные фабрики `none()` и `fromString()`,
  добавить `value(): ?string`.
- Для `MediaMultipartPart` заменить `fromDatabaseValues()` на доменное имя
  `fromValues()` или использовать существующий `create()` с готовыми VO.
- Для `MediaMultipartPartCollection` оставить доменные методы `fromParts()` и
  `jsonSerialize()`, а JSON decode/encode перенести в typecast.
- Переписать `App\Shared\Infrastructure\Cycle\ValueObjectCast`:
  - принимать `BackedEnum`;
  - принимать простые VO-классы с фабриками `fromString()` или `fromInt()`;
  - принимать отдельные typecast-классы;
  - при `cast()` использовать правило конкретного поля;
  - при `uncast()` сначала использовать правило конкретного поля, затем
    обрабатывать `BackedEnum`, `DateTimeInterface` и скаляры;
  - для объекта без правила бросать `TypecastException`.
- Создать инфраструктурный контракт
  `App\Shared\Infrastructure\Cycle\ColumnValueTypecast` для отдельных
  typecast-классов:

```php
public static function castDatabaseValue(
    bool|int|float|string|\DateTimeInterface|null $value,
): object|null;

public static function uncastValue(
    object|null $value,
): bool|int|float|string|\DateTimeInterface|null;
```

- В `ValueObjectCast` применять `ColumnValueTypecast` только как правило поля из
  `#[Column(typecast: ...)]`. Доменные `ValueObject` не реализуют этот контракт.
- Создать отдельные typecast-классы для сложных медиа-значений:
  - `MediaExpirationTypecast`;
  - `MediaProcessingErrorTypecast`;
  - `MediaMultipartPartCollectionTypecast`.
- Обновить `#[Column(typecast: ...)]`:
  - простые VO оставлять как `MediaPath::class`, `MediaId::class` и так далее;
  - сложные nullable / JSON поля перевести на отдельные typecast-классы.
- Обновить репозитории: вместо `toDatabase()` использовать `value()` или
  доменный метод, который явно отдаёт значение для поиска.
- Обновить unit-тесты `ValueObject` и `ValueObjectCast` под новую модель.
- Добавить тесты на сложные typecast-классы:
  - nullable date;
  - nullable processing error;
  - JSON collection multipart parts;
  - ошибка при неподдерживаемом объекте.

Результат: в `Modules/Media/Domain` нет зависимостей на Cycle ORM и
`Shared\Infrastructure`, но ORM продолжает сохранять и читать VO.

Сценарии тестирования:

- каждый VO создаётся через доменную фабрику и валидирует вход;
- `ValueObjectCast` восстанавливает простые строковые, UUID и integer VO;
- отдельные typecast-классы восстанавливают nullable и JSON-значения;
- entity гидрируются из БД с VO-свойствами;
- репозитории ищут по VO без `toDatabase()`;
- ошибка typecast содержит имя поля.
- чтение существующих строк медиа из тестовой БД не падает из-за усиленной
  валидации `fromString()` / `fromInt()`.

Проверка:

- Запустить `make test`.
- Запустить `make phpstan`.
- Дополнительно проверить поиском:

```text
rg "fromDatabase|toDatabase|Castable|JsonCastable|Shared\\Infrastructure" app/src/Modules/Media/Domain
```

### 4. Обновить документацию и финальные проверки

Цель: убрать противоречия между кодом, правилами и архитектурой.

Что сделать:

- Обновить `docs/arch.md`: заменить старую структуру `Endpoint`, `Application`,
  `Domain`, `Repository`, `Infrastructure` на целевую структуру `Modules` и
  `Shared`.
- В `docs/arch.md` добавить согласованный текст про `Repository` как отдельный
  слой модуля, `Application/Contract`, суффикс `Contract` и
  `Infrastructure/FileService`.
- Обновить `docs/rules.md`:
  - заменить старые примеры `App\Domain`, `App\Repository`, `App\Endpoint` на
    модульные namespace;
  - исправить правило про Filter-ы: они живут в
    `Modules/{Module}/Presentation`, а не в корневом `Endpoint`;
  - исправить правило CQRS: сценарии живут в
    `Modules/{Module}/Application`;
  - уточнить правило репозиториев: репозиторий лежит в `Modules/{Module}/Repository`,
    доступен своему `Application` и не является публичным входом для других
    модулей;
  - уточнить правило контрактов: контракты технических сервисов лежат в
    `Application/Contract` и имеют суффикс `Contract`;
  - заменить команду `composer phpstan` на `make phpstan`;
  - добавить команду тестов `make test`;
  - исправить опечатку `покрут` на `покрыт`.
- Обновить `docs/code-examples.md` под `Modules` и чистые `ValueObject`.
- Проверить, что в документации не осталось старых обязательных путей для нового
  кода.

Результат: документы описывают ту же архитектуру, что реализована в коде.

Сценарии тестирования:

- примеры кода не используют старые namespace;
- команды проверок в правилах совпадают с Docker-командами проекта;
- архитектура не содержит противоречивой старой структуры как целевого состояния.
- документация описывает `Shared\Domain\Exception` как место для общих доменных
  исключений.
- документация описывает `Shared\Domain\Trait` как место для общих доменных
  трейтов.

Проверка:

- Запустить `make test`.
- Запустить `make phpstan`.
- Проверить поиском по документации:

```text
rg "App\\Domain|App\\Repository|App\\Endpoint|App\\Infrastructure|composer phpstan|покрут" docs
```

## Тесты

Стратегия: тесты обновляются и запускаются после каждой фазы.

Обязательные проверки:

- после каждой фазы запускать `make test`;
- после каждой фазы запускать `make phpstan`;
- для переноса namespace дополнительно использовать `rg`, чтобы найти старые
  imports;
- для `ValueObjectCast` покрыть простые VO, enum, nullable VO, JSON collection и
  ошибку неподдерживаемого объекта;
- для медиа-репозиториев сохранить feature-тесты чтения и записи через БД;
- для HTTP-слоя сохранить feature-тест health endpoint и OpenAPI.
- для `openapi:generate` проверить, что YAML после генерации содержит `/health`
  из нового namespace.
- для Temporal добавить проверку `Ping::handle()` и наличия workflow class после
  переноса.
- учитывать, что `phpstan.neon` проверяет `app/src`, а не `tests`; ошибки
  namespace в тестах ловятся запуском `make test`.

Тесты нельзя запускать через `composer test` с хоста. Проверки приложения
запускаются через Docker-команды `make test` и `make phpstan`.

## Логирование

Стратегия: `debug_precise`.

В этом плане нет новой бизнес-операции, поэтому новые runtime-логи не
добавляются.

Требования к диагностике:

- сохранить существующий debug output OpenAPI generation;
- сообщения `TypecastException` должны содержать имя поля и техническую причину;
- сообщения typecast-ошибок не должны содержать секреты, токены, пароли и
  пользовательские файлы целиком;
- временные debug-выводы, добавленные при переносе namespace, не коммитятся.

## Документация и эксплуатация

Обновить:

- `docs/arch.md`;
- `docs/rules.md`;
- `docs/code-examples.md`.

Эксплуатационно важно:

- БД не меняется, миграции и backfill не нужны.
- Перед релизом на окружении с реальными данными нужно прогнать чтение медиа-строк
  через репозитории. Это проверяет, что усиленная валидация VO не ломает
  гидрацию старых значений.
- После релиза нужно пересобрать кэш Cycle schema обычным запуском приложения или
  штатной командой проекта.
- Runtime остаётся прежним: RoadRunner, Temporal, PostgreSQL, Redis, MinIO.
- Внешние API и console-команды остаются совместимыми.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** перенос общих доменных исключений в
  `App\Shared\Domain\Exception`.
- **+ Добавлено:** перенос общего трейта `HasTimestamps` в
  `App\Shared\Domain\Trait`.
- **+ Добавлено:** перенос `AbstractResource`, тестовой инфраструктуры
  `tests/App`, правка `app/config/openapi.php` и `app/config/cache.php`.
- **+ Добавлено:** явный контракт `ColumnValueTypecast` для отдельных
  typecast-классов.
- **+ Добавлено:** проверки OpenAPI generation, Temporal ping и чтения
  существующих медиа-строк.
- **+ Добавлено:** правило структуры для контрактов, репозиториев и файловых
  сервисов: `Application/Contract`, `Repository`, `Infrastructure/FileService`.
- **~ Изменено:** репозитории вынесены из `Infrastructure` в отдельный слой
  модуля, обязательные `RepositoryContract` убраны.
- **+ Добавлено:** согласованный текст в `docs/arch.md` про модульный монолит с
  прагматичным DDD-подходом.
- **~ Изменено:** фаза 1 теперь переносит общий код в `Shared`, а не только
  инфраструктуру.
- **~ Изменено:** проверки старых namespace расширены на `app.php`,
  `app/config`, `tests` и `docs`.
- **Отклонено:** нет.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-22_15-24_valueobject-modular-monolith.md`

- [x] Шаг 1: Перенести общий код в Shared.
- [x] Шаг 2: Перенести код в модули.
- [x] Шаг 3: Сделать ValueObject независимыми от Cycle ORM.
- [x] Шаг 4: Обновить документацию и финальные проверки.
