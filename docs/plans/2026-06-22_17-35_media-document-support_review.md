Reading additional input from stdin...
OpenAI Codex v0.141.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
model: gpt-5.5
provider: openai
approval: never
sandbox: workspace-write [workdir, /tmp, $TMPDIR]
reasoning effort: high
reasoning summaries: none
session id: 019eefce-a763-7243-bf2d-8eeb95d89e3f
--------
user
Прочитай docs/plans/2026-06-22_17-35_media-document-support.md, docs/rules.md, docs/arch.md и связанное исследование docs/researches/2026-06-22_17-22_media-document-support.md. Затем глубоко проверь план по релевантному коду модуля Media: открой app/src/Modules/Media/Application/Service/MediaTypeResolver.php, app/src/Modules/Media/Domain/ValueObject/MediaPath.php, app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php, app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php, app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php, app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php, app/src/Modules/Media/Domain/Enum/MediaType.php и связанные тесты, и оцени фактическую реализуемость. Задача плана — снять заглушку MediaType::Document в 5 точках кода, чтобы документы загружались и отдавались как медиа без конверсий, не ломая image/video/audio. Критическое ревью строго на русском, только по реальным проблемам: пропущенные шаги, неучтённые зависимости, нарушения docs/rules.md и docs/arch.md, скрытые риски, риски для смежного кода, недостающие тесты, шаги без критериев готовности. Каждая строка в формате '- [тип] описание — что предлагаешь'. Файлы не меняй.
codex
Сначала читаю план, правила, архитектуру, исследование и указанные файлы модуля Media. После этого отдельно найду связанные тесты, чтобы сверить план с фактическим покрытием.
exec
/bin/zsh -lc "sed -n '1,240p' docs/plans/2026-06-22_17-35_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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

 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:35
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
  research: docs/researches/2026-06-22_17-22_media-document-support.md
---

# План реализации

## Задача

Включить в модуле `Media` загрузку и выдачу документов (PDF, DOC, DOCX, DJVU, TXT и другие
ходовые форматы). Сейчас `MediaType::Document` объявлен в enum, но это заглушка: документ
сознательно отклоняется во всех точках пайплайна. Нужно снять заглушку так, чтобы документ
можно было загрузить, переложить в постоянное хранилище и отдать по URL, **не ломая** уже
работающий поток image/video/audio.

Готово, когда: документ проходит весь путь `RequestMediaUpload → CompleteMediaUpload →
ProcessMedia → GetMediaUrl` как «медиа без конверсий»; `make test` и `make phpstan` зелёные;
покрытие тестами 100%.

## Контекст

Документ в выбранной модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan` пустой и
обработчик ничего не транскодирует. Доменная и архитектурная модель НЕ меняется — нет новых
Entity, контрактов, репозиториев, зависимостей, миграций, конфигов, очередей, outbox-сообщений
или ключей локали. Меняются ровно те 5 точек, где `MediaType::Document` сейчас бросает
исключение, плюс затрагиваемые тесты.

Точки, проверенные по коду:

```text
1 MediaTypeResolver::resolve()              app/.../Application/Service/MediaTypeResolver.php:18-38
2 MediaPath::assertValid() (regex)          app/.../Domain/ValueObject/MediaPath.php:154
3 MediaPath::originalReady()                app/.../Domain/ValueObject/MediaPath.php:83
4 ProcessMediaHandler::handle() match       app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
5 CompleteMediaUploadHandler::assertPlanValid()  app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
```

Важные факты из кода:

- **У модуля Media нет HTTP-роутов и контроллеров.** `RequestMediaUpload`,
  `CompleteMediaUpload`, `GetMediaUrl` — это CQRS Application-сценарии (Command/Query + Handler),
  которые потребляются другими модулями (например `User`, `Posts`) через `Media/Application`.
  Единственный вход в `Media/Presentation` — технический `ProcessMediaJob`. Поэтому «правок
  роутов/контроллеров/OpenAPI» в этой задаче нет, а проверка идёт feature-тестами Handler'ов и
  end-to-end Flow-тестом.
- Резолвер вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): потребитель спецификации может только сузить набор,
  заданный whitelist'ом резолвера, но не добавить новый MIME. Значит любой принимаемый MIME
  документа обязан быть в whitelist кода.
- Расширение файла берётся из имени файла (`pathinfo($fileMeta->fileName, PATHINFO_EXTENSION)`
  в `RequestMediaUploadHandler::extension()`), а НЕ из MIME. Поэтому резолверу нужен только
  **набор MIME** документов; таблица «MIME → расширение» из research — справочная.
- `MediaPath::originalUpload()` (staging-путь `uploads/...`) от типа не зависит — не трогаем.
- `MediaConversionPlan` (три списка `image/video/audio`) и `MediaUploaded` не трогаем: для
  документа все три списка пусты.
- `MediaTypeResolver` — `final readonly` без зависимостей (чистый сервис); `MediaPath` — доменный
  VO (классового докстринга у него нет). Логгер в них не вводится (см. раздел «Логирование»).
- Смежные точки проверены и правок НЕ требуют: typecast `MediaType` уже поддерживает значение
  `document`; `GetMediaUrl` отдаёт оригинал по `visibility` без зависимости от типа;
  `S3MediaFileService` оперирует абстрактным `MediaPath` и префикс не хардкодит; staging-expiry
  чистит документы как и другие типы.

## Принятые решения

Все существенные решения подтверждены пользователем в research
(`docs/researches/2026-06-22_17-22_media-document-support.md`, раздел «Ответы на вопросы»):

- **Поведение** — документ только хранится: после подтверждения оригинал перекладывается в
  постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`. Конверсий, превью первой
  страницы и извлечения метаданных нет. *(подтверждено пользователем)*
- **Распознавание типа** — явный whitelist MIME в `MediaTypeResolver`. Неизвестный
  `application/*` по-прежнему 422 `unsupported_file_type`. *(подтверждено пользователем)*
- **Набор форматов** — все четыре категории (полный whitelist в research, раздел «Whitelist
  MIME → расширение»). Whitelist зафиксирован пользователем; добавление прочих вариантов MIME
  (например `text/x-rtf`, `image/svg+xml`) в эту задачу не входит. *(подтверждено пользователем)*
- **Порядок проверок в резолвере** — whitelist документов сравнивается ДО префиксов
  `image/`/`video/`/`audio/`, потому что DJVU имеет MIME `image/vnd.djvu`; иначе он стал бы
  Image. *(закрытие риска из research)*
- **Нормализация MIME — только для сравнения с whitelist.** Резолвер вычисляет нормализованный
  MIME (нижний регистр + отбрасывание параметров после `;`, `text/markdown;charset=utf-8` →
  `text/markdown`) и сравнивает с whitelist по нему. Три префиксные проверки image/video/audio
  остаются на **исходном** `$value` — поведение этих типов не меняется (минимальная правка,
  совпадает с формулировкой research «перед сравнением с whitelist»). *(закрытие риска из
  research)*
- **Представление whitelist** — приватная типизированная константа `list<string>` в
  `MediaTypeResolver` (нормализованные MIME в нижнем регистре), членство — `\in_array(...,
  strict: true)`. По `decision_mode: recommend_and_ask` это деталь реализации, не новое
  существенное решение (нет новых зависимостей/БД/API): закрытый набор вариантов рядом с
  использованием — по правилу «Не плодить технические константы» это уместная константа.

Ожидаемый объём (plan_size: normal): 5 правок кода в 4 файлах + правки/добавления в 5 тестовых
файлах; миграций и новых файлов нет.

## Целевой алгоритм

```text
RequestMediaUpload
  resolve(mime): normalized = lower(strip_params(mime))
                 если normalized в whitelist документов ⇒ Document;
                 иначе по исходному value префикс image/ | video/ | audio/;
                 иначе 422 unsupported_file_type
  allowedMimeTypes сужает набор спецификацией потребителя (может отклонить документ как
                 mime_not_allowed, но не добавить новый MIME)
  media = waitingUpload, path = uploads/<shard>/<key>/source.<ext>  (staging, тип не влияет)
  клиент PUT-ит байты в staging-бакет

CompleteMediaUpload
  headObject подтверждает размер
  assertPlanValid(type=Document): все три списка плана (image/video/audio) ДОЛЖНЫ быть пусты,
                 иначе 422 conversion_plan_type_mismatch
  media = uploaded, MediaUploaded(plan=пустой) в outbox

outbox:relay → ProcessMediaJob → ProcessMedia
  match(type): Document ⇒ конверсий нет ([])
  persistReady: copyObject(uploads/... → documents/<shard>/<key>/source.<ext>),
                media → ready (storage = public|private по visibility)

GetMediaUrl
  public: прямой URL; private: presignGet  (без изменений)
```

## Контракты реализации

### Данные и БД

`Не затрагивается`. Колонка `media.type` уже хранит значение `document` (enum `MediaType`
объявлен с этим case-ом, Entity и typecast его уже поддерживают). Миграций, новых таблиц,
полей, индексов нет. Старые данные совместимы: документов в БД ещё не было, потому что загрузка
отклонялась раньше записи.

### API и внешние контракты

`Не затрагивается` в смысле внешних HTTP-контрактов: у модуля Media нет собственных
HTTP-роутов, контроллеров, Filter и Response-классов (см. «Контекст»). Затрагиваемые
`RequestMediaUpload`/`CompleteMediaUpload`/`GetMediaUrl` — внутренние Application-сценарии
(Command/Query + Handler) для других модулей; их сигнатуры и DTO не меняются. Новых ошибок,
status code, webhook'ов, очередей, outbox-сообщений нет.

Поведенческое расширение (в рамках существующих Application-сценариев):

```text
RequestMediaUpload  — теперь резолвит whitelist MIME документов в Document вместо 422.
                      Конкретный потребитель примет документ, только если его MediaUploadSpec
                      .allowedMimeTypes включает нужный MIME (потребитель сужает набор; правка
                      потребителя — вне рамок модуля Media).
CompleteMediaUpload — для документа ожидает пустой план конверсий; непустой план ⇒ 422
                      app.media.conversion_plan_type_mismatch (ключ уже есть в locale).
ProcessMedia        — для документа перекладывает оригинал в documents/... без конверсий.
unsupported_file_type остаётся для реально неизвестных MIME (например font/woff2).
```

Новых ключей локали не требуется (`conversion_plan_type_mismatch`, `unsupported_file_type` уже
есть в `app/locale/ru/media.php` и `app/locale/en/media.php`).

## Фазы выполнения

### 1. Классификация документа в `MediaTypeResolver`

Цель: резолвер распознаёт документ по whitelist MIME раньше префиксных правил и устойчив к
регистру и параметрам MIME.

Что сделать:
- Добавить в `MediaTypeResolver` приватную типизированную константу `DOCUMENT_MIME_TYPES`
  (`@var list<string>`) с нормализованными MIME из whitelist research (все в нижнем регистре):
  `application/pdf`, `application/msword`,
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `text/plain`,
  `application/rtf`, `text/rtf`, `application/vnd.oasis.opendocument.text`,
  `application/vnd.oasis.opendocument.spreadsheet`,
  `application/vnd.oasis.opendocument.presentation`, `application/vnd.ms-excel`,
  `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
  `application/vnd.ms-powerpoint`,
  `application/vnd.openxmlformats-officedocument.presentationml.presentation`,
  `application/epub+zip`, `application/x-fictionbook+xml`, `image/vnd.djvu`, `text/csv`,
  `text/markdown`. Рядом с константой — короткий комментарий, что macro-enabled форматы
  (MIME с суффиксом `…macroEnabled.12`) намеренно НЕ включены (активное содержимое).
- Добавить приватный метод нормализации: нижний регистр + отбросить часть после `;` + `trim`.
- В `resolve()`: вычислить нормализованный MIME, **первым** проверить членство в
  `DOCUMENT_MIME_TYPES` через `\in_array(needle: $normalized, haystack: self::DOCUMENT_MIME_TYPES,
  strict: true)` → `MediaType::Document`; затем существующие три префиксные проверки `str_starts_with`
  **на исходном `$value`** (поведение image/video/audio не меняется); затем существующий
  `throw 422 unsupported_file_type`.
- Обновить классовый докстринг резолвера (строки 11-15): сейчас он утверждает, что документы
  отклоняются и что `MediaPath` допускает только `uploads|images|videos|audios` — после правки
  оба утверждения становятся ложными. Привести формулировку в соответствие (поддержан whitelist
  MIME документов; `MediaPath` допускает и префикс `documents`).

Результат: `resolve('application/pdf') === Document`, `resolve('image/vnd.djvu') === Document`
(не Image), `resolve('text/markdown;charset=utf-8') === Document`, `resolve('image/jpeg') ===
Image`, `resolve('image/png') === Image` (прочий image/* не перехвачен whitelist),
`resolve('font/woff2')` → `ValidationException`.

Критерий готовности докстринга: в докблоке резолвера нет фразы про «документы отклоняются» и про
«только uploads|images|videos|audios»; есть упоминание whitelist документов и префикса
`documents`.

Сценарии тестирования (после фазы) — файл `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php`:
- Позитивные кейсы документов: `application/pdf`, `application/msword`, `text/csv`,
  `text/markdown` → `Document`.
- DJVU `image/vnd.djvu` → `Document`, а НЕ `Image` (порядок проверок).
- Нормализация: `text/markdown;charset=utf-8` → `Document`; `APPLICATION/PDF` и `TEXT/CSV`
  (верхний регистр) → `Document`.
- `image/png` → `Image` (whitelist не перехватывает прочие image/* — кроме djvu).
- `unsupportedMimeTypeProvider` (строки 37-40): убрать ставшие поддержанными `application/pdf` и
  `text/plain`; оставить/добавить реально неподдержанные `font/woff2`, `application/zip`,
  `application/octet-stream` и macro-enabled
  `application/vnd.ms-word.document.macroEnabled.12` (подтверждает исключение макросов).

Проверка: `make test` (новые кейсы зелёные), `make phpstan`.

### 2. Префикс `documents` в `MediaPath`

Цель: путь готового оригинала документа валиден и строится с префиксом `documents`.

Что сделать:
- В `MediaPath::assertValid()` (строка 154) расширить регулярку префиксов:
  `(uploads|images|videos|audios)` → `(uploads|images|videos|audios|documents)`. Проверка
  shard↔uuid (строки 162-164) общая для всех префиксов и не меняется.
- В `MediaPath::originalReady()` (строки 79-86) в `match($type)` заменить ветку
  `MediaType::Document => throw ...` на `MediaType::Document => 'documents'`. `match` остаётся
  исчерпывающим без `default`.
- `originalUpload()` не трогаем.

Результат: `MediaPath::originalReady(type: Document, extension: 'pdf')` →
`documents/<shard>/<key>/source.pdf`; `MediaPath::fromString('documents/aa/<uuid>/source.pdf')`
валиден; пути image/video/audio и uploads — без изменений.

Сценарии тестирования (после фазы) — файл

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
- **Не подменять методы коллекции array-функциями**: если значение уже `Illuminate\Support\Collection` (в т.ч. типизированная доменная коллекция), для выборок и трансформаций вызывай её методы (`->filter()`, `->map()`, `->first()`, `->last()`, `->take()`, `->contains()`, `->reject()`, `->keyBy()`), а не разворачивай в массив через `->all()` ради `array_filter`/`array_map`/`array_slice`/`\count()`. Антипаттерн: `array_filter($collection->all(), $fn)` вместо `$collection->filter($fn)`. `->all()` уместен только когда наружу действительно нужен `array`/`list<T>` (метод объявляет такой тип возврата, передаём в чужой API), а не как промежуточный шаг между двумя коллекциями. Подробности и идиомы — в скилле [`laravel-collections`](../.claude/skills/laravel-collections/SKILL.md).
- **Без round-trip «коллекция → массив → коллекция»**: запрещено брать `->all()`, обрабатывать массив (`\count()`, `array_slice()`, индексирование `$page[\count($page) - 1]`) и заворачивать результат обратно в `new SomeCollection($array)`. Если на входе и на выходе коллекция, весь конвейер остаётся на коллекции. Чтобы преобразовать типизированную коллекцию в другой тип элемента (ресурсы, DTO), используй `$collection->toBase()->map($fn)` — базовый `Collection` не ломает дженерик `final`-коллекции, — а не `new Collection($collection->all())`; в конструктор другой типизированной коллекции передавай `Collection`/`Arrayable` напрямую, без `->all()`.
- **Cursor-пагинация — через общие примитивы, не копипастом**: схему «взять `limit + 1`, понять, есть ли следующая страница, отдать первые `limit` и курсор последней отданной» не дублировать в каждом репозитории и обработчике. Используются два общих примитива:
  - **Репозиторий**: `App\Shared\Infrastructure\Cycle\WhenSelect::cursorById($cursor?->value(), $limit)` — последнее звено цепочки `select()`, инкапсулирует `id DESC` + `where('id', '<', $cursor)` при курсоре + `limit`. Не писать вручную блок `->when(...cursor...)->orderBy('id','DESC')->limit($limit)` в каждом методе.
  - **Обработчик (Query)**: `App\Shared\Domain\Pagination\CursorSlice::fromOverfetched(overfetched: $repoResult, limit: $query->limit, cursorOf: static fn(Entity $e): string => $e->id->value())` — возвращает `$slice->items` (та же типизированная коллекция, первые `limit`) и `$slice->nextCursor`. Не писать вручную `$page->take($limit)` + `$page->count() > $limit` + `$visible->last()?->id->value()` в каждом обработчике.

  Антипаттерн (исправлен в `GetUserFeedHandler`, `GetPostCommentsHandler`, `GetCommentRepliesHandler`, `ListNotificationsHandler` и cursor-методах `PostRepository`/`CommentRepository`/`NotificationRepository`): `->all()` → `\count()` → `array_slice()` → `new XCollection($visible)` → `$visible[\count($visible) - 1]`, а также копия `when(cursor)+orderBy+limit` в каждом запросе.
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

exec
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-22_17-22_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:22
mode: strict
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Поддержка документов в модуле Media

## Суть

В модуле `Media` нужно включить загрузку и выдачу документов (PDF, DOC, DOCX, DJVU,
TXT и другие популярные форматы). Сейчас `MediaType::Document` объявлен в enum, но это
заглушка: документы сознательно отклоняются во всех точках пайплайна. Задача — снять эту
заглушку так, чтобы документ можно было загрузить, переложить в постоянное хранилище и
отдать по URL, не ломая уже работающий поток image/video/audio.

Что именно отклоняется сейчас (точки проверены по коду):

```text
MediaTypeResolver::resolve()           app/.../Application/Service/MediaTypeResolver.php:22-37
  — распознаёт только image/*, video/*, audio/*; остальное → 422 unsupported_file_type
MediaPath::assertValid() (regex)       app/.../Domain/ValueObject/MediaPath.php:154
  — путь допускает лишь префиксы uploads|images|videos|audios
MediaPath::originalReady()             app/.../Domain/ValueObject/MediaPath.php:83
  — MediaType::Document => throw InvalidDomainValueException
ProcessMediaHandler::handle()          app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
  — MediaType::Document => throw «Обработка документов не поддержана»
CompleteMediaUploadHandler             app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
  — MediaType::Document => throw ValidationException в assertPlanValid()
```

## Решение

### Что выбрано

- **Поведение**: документ только хранится. После подтверждения загрузки оригинал
  перекладывается в постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`.
  Конверсий, превью первой страницы и извлечения метаданных нет.
- **Распознавание типа**: явный whitelist MIME-типов документов в `MediaTypeResolver`.
  Неизвестный `application/*` по-прежнему отклоняется как 422.
- **Набор форматов**: все четыре согласованные категории (полный список ниже).

Документ в этой модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan`
полностью пустой, и обработчик ничего не транскодирует.

### Почему так, а не иначе

| Развилка | Выбор | Почему |
|---|---|---|
| Глубина обработки | Только хранение | Совпадает с природой Media («храним и отдаём URL»). Превью потребовало бы Ghostscript + LibreOffice headless в образе, новую таблицу конверсий, новый процессор и контракт — отдельная большая задача. Превью можно добавить позже по образцу видео-постера. |
| Распознавание | Явный whitelist MIME | Сохраняет «намеренно сужающую» философию резолвера: модуль принимает только то, что знает. Предсказуемо и тестируемо. Альтернатива «всё, что не image/video/audio — документ» приняла бы любой `application/octet-stream`. |
| Набор форматов | Все 4 категории | Покрывает названные пользователем PDF/DOC/DOCX/DJVU/TXT и ходовые офисные/книжные форматы. |

### Архитектура: какие слои затронуты

Доменная и архитектурная модель НЕ меняется — нет новых Entity, контрактов, репозиториев,
зависимостей, миграций, конфигов, очередей или outbox-сообщений. Меняются ровно те 5 точек,
где `MediaType::Document` сейчас бросает исключение. Поток данных остаётся прежним:

```text
RequestMediaUpload  -> presigned PUT, media = waitingUpload   (тип резолвится в Document)
   клиент PUT-ит байты в staging-бакет
CompleteMediaUpload -> headObject, media = uploaded, MediaUploaded в outbox  (план пустой)
outbox:relay -> ProcessMediaJob -> ProcessMedia
   match(type): Document -> конверсий нет ([]) -> перекладка оригинала -> ready
GetMediaUrl -> public: прямой URL; private: presignGet
```

Точки правок:

| Точка | Сейчас | Станет |
|---|---|---|
| `MediaTypeResolver::resolve()` | 3 префиксных правила, остальное 422 | нормализовать MIME (нижний регистр, без параметров); whitelist MIME → `Document` проверяется **первым**, затем префиксы image/video/audio, затем 422 |
| `MediaPath::assertValid()` regex | `(uploads\|images\|videos\|audios)` | добавить префикс `documents` |
| `MediaPath::originalReady()` | `Document => throw` | `Document => 'documents'` |
| `ProcessMediaHandler::handle()` match | `Document => throw` | `Document => []` (пустой набор конверсий, дальше штатная перекладка `persistReady`) |
| `CompleteMediaUploadHandler::assertPlanValid()` | `Document => throw` | `Document =>` проверка, что `image`/`video`/`audio` пусты; иначе 422 `conversion_plan_type_mismatch` |

`originalUpload()` не трогаем: staging-путь `uploads/...` от типа не зависит.
`MediaConversionPlan` и `MediaUploaded` не трогаем: для документа все три списка пусты.
Перевод `app.media.conversion_plan_type_mismatch` уже есть (`app/locale/ru/media.php:18`) —
подходит для непустого плана у документа; `unsupported_file_type` остаётся для реально
неизвестных MIME.

### Whitelist MIME → расширение (формат)

`MediaPath::sanitizeExtension()` допускает `[a-z0-9]+` — все расширения ниже проходят.

```text
Базовые текстовые
  application/pdf                                                              -> pdf
  application/msword                                                           -> doc
  application/vnd.openxmlformats-officedocument.wordprocessingml.document      -> docx
  text/plain                                                                   -> txt
  application/rtf, text/rtf                                                    -> rtf   (встречаются оба MIME)
OpenDocument
  application/vnd.oasis.opendocument.text                                      -> odt
  application/vnd.oasis.opendocument.spreadsheet                               -> ods
  application/vnd.oasis.opendocument.presentation                              -> odp
MS Office таблицы/презентации
  application/vnd.ms-excel                                                     -> xls
  application/vnd.openxmlformats-officedocument.spreadsheetml.sheet            -> xlsx
  application/vnd.ms-powerpoint                                                -> ppt
  application/vnd.openxmlformats-officedocument.presentationml.presentation    -> pptx
Книги и сканы
  application/epub+zip                                                         -> epub
  application/x-fictionbook+xml                                                -> fb2   (MIME не стандартизирован IANA)
  image/vnd.djvu                                                               -> djvu  (внимание: префикс image/)
  text/csv                                                                     -> csv   (параметры charset/header нормализуются)
  text/markdown                                                                -> md    (charset нормализуется до базового MIME)

Явно НЕ поддержаны (не в whitelist): macro-enabled Office (docm/xlsm/pptm,
MIME с суффиксом …macroEnabled.12) — активное содержимое.
```

Сравнение с whitelist — по нормализованному MIME: базовый тип в нижнем регистре, без
параметров после `;` (нужно для `text/markdown;charset=…` и `text/csv;charset=…`).

### Риски — закрытие рядом с решением

- **DJVU ломает порядок проверок в резолвере.** MIME DJVU — `image/vnd.djvu`, он начинается
  с `image/`. Текущий резолвер проверяет `str_starts_with('image/')` первым
  (`MediaTypeResolver.php:22`), поэтому без изменений DJVU стал бы `Image` и пайплайн отправил
  бы его в Imagick-ресайз. **Снимаем**: whitelist документов (точное сравнение MIME)
  проверяется ДО префиксных правил. Это обязательное требование к реализации резолвера.
- **Whitelist резолвера — единственный источник набора, потребитель его не расширяет.**
  `MediaTypeResolver::resolve()` вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): сначала тип резолвится, и неизвестный резолверу MIME
  падает в 422 раньше, чем дойдёт до спецификации потребителя. Значит `allowedMimeTypes` может
  только сузить набор, заданный whitelist'ом, но не добавить новый MIME. Это влияет на
  формулировки ниже (FB2/CSV/RTF) и означает: любой принимаемый MIME должен быть в whitelist кода.
- **Модуль доверяет заявленному MIME и не проверяет содержимое.** Клиент PUT-ит байты прямо в
  S3, MIME приходит из `MediaFileMeta`. Это свойство существующей архитектуры (так же для
  image/video/audio). Для документов добавляется поверхность (вредоносный PDF; офисные файлы с
  активным содержимым), но Media документ не парсит и не исполняет — только хранит и отдаёт.
  **Принимаем** в рамках текущего дизайна; антивирус/проверка magic bytes — отдельная задача вне
  этих рамок. Отдельно про макросы: macro-enabled форматы (`docm`/`xlsm`/`pptm`, MIME с суффиксом
  `…macroEnabled.12`) в whitelist НЕ входят и остаются неподдержанными. Старый `doc`
  (`application/msword`) включён по запросу и теоретически может нести макросы, но риск нивелируется
  тем, что модуль контент не исполняет.
- **Параметры в MIME (charset/header).** По IANA у `text/markdown` параметр `charset` обязателен,
  у `text/csv` допустимы `charset` и `header`; клиент реально присылает, например,
  `text/markdown;charset=utf-8`. `MediaMimeType` хранит строку целиком и параметры не отделяет, а
  whitelist — точное сравнение, поэтому `text/markdown;charset=utf-8` не совпало бы с
  `text/markdown` и markdown/csv фактически не прошли бы. **Снимаем**: резолвер перед сравнением с
  whitelist нормализует MIME — отбрасывает параметры (часть после `;`) и приводит к нижнему
  регистру (`text/markdown;charset=UTF-8` → `text/markdown`). Это требование к реализации
  резолвера; нормализацию покрыть тестом.
- **MIME FB2 не стандартизирован.** `application/x-fictionbook+xml` — самый частый, но встречаются
  `application/fb2` и сырой `application/xml`. **Снимаем выбором**: в whitelist берём канонический
  `application/x-fictionbook+xml`; остальные MIME для FB2 считаются неподдержанными и при
  необходимости добавляются в whitelist правкой кода (потребитель их не включит — см. пункт о
  порядке resolve выше).
- **CSV/RTF имеют несколько MIME.** RTF — `application/rtf` и `text/rtf`; CSV — `text/csv` (Excel
  иногда помечает иначе). **Снимаем**: для RTF включаем в whitelist оба MIME; для CSV берём
  канонический `text/csv`. Прочие варианты — не «на усмотрение потребителя» (он whitelist не
  расширяет), а неподдержанные до явного добавления в whitelist.
- **Осиротевшие staging-объекты** — без изменений: документ, как и другие типы, чистится
  staging-expiry задачей; терминальные сироты — на будущий storage-sweep.

### Версии ПО

Проверка актуальных версий пакетов/ПО **не требуется**: выбран вариант «только хранение»,
он не вводит новых зависимостей (Composer-пакетов, бинарей, системных пакетов). Imagick и
ffmpeg для документов не задействуются; Ghostscript/LibreOffice не нужны, потому что превью
отклонено. Используются только уже имеющиеся S3-операции (`copyObject`, `headObject`,
`presignPut`, `presignGet`).

## Ответы на вопросы

| Вопрос (развилка) | Ответ пользователя |
|---|---|
| Что Media делает с документом после загрузки? | **Только хранить оригинал** (без превью и без метаданных) |
| Как определять, что файл — документ? | **Явный список MIME-типов** (whitelist) |
| Какие форматы включить? | **Все четыре категории**: базовые текстовые (PDF, DOC, DOCX, TXT, RTF), OpenDocument (ODT, ODS, ODP), MS Office таблицы/презентации (XLS, XLSX, PPT, PPTX), книги и сканы (EPUB, FB2, DJVU, CSV, MD) |

## Итог

Подход: снять заглушку `MediaType::Document` в 5 уже найденных точках, не вводя новых
сущностей, зависимостей, миграций и конфигов. Документ обрабатывается как «медиа без
конверсий»: резолвер нормализует MIME (нижний регистр, без параметров) и классифицирует его
по whitelist MIME (с приоритетом над префиксом
`image/` ради DJVU), `MediaPath` получает префикс `documents`, `ProcessMediaHandler`
возвращает пустой набор конверсий и делает штатную перекладку оригинала в ready, а
`CompleteMediaUpload` требует, чтобы план конверсий для документа был пустым. Полный whitelist
MIME → расширение зафиксирован выше. Тесты: дополнить `MediaTypeResolverTest` (включая
DJVU → Document, а не Image, и MIME с параметром `;charset=…`), `MediaValueObjectTest` (путь
`documents`), `ProcessMediaHandlerTest` (перекладка без конверсий), `CompleteMediaUploadHandlerTest`
(пустой план для документа), `RequestMediaUploadHandlerTest` (запрос загрузки документа) —
требуется 100% покрытие. Следующий шаг — `eda-plan` для пошаговой реализации.

## Реакция на ревью

Кросс-ревью выполнено Codex CLI (`codex-cli 0.141.0`); полный лог —
`2026-06-22_17-22_media-document-support_review.md`. Codex дал 3 содержательных замечания.

- **Принято [архитектура] — порядок resolve vs allowedMimeTypes.** Формулировка «другие MIME
  (FB2/CSV/RTF) — ответственность потребителя через `allowedMimeTypes`» была неверной: `resolve()`
  вызывается до проверки `allowedMimeTypes` (`RequestMediaUploadHandler.php:40-41`), поэтому
  потребитель whitelist только сужает. Добавлен отдельный пункт-риск про порядок, переписаны
  пункты FB2 и CSV/RTF.
- **Принято [MIME] — параметры MIME (charset/header).** Точное сравнение не пропустило бы
  `text/markdown;charset=utf-8` / `text/csv;charset=…`. Добавлено решение: резолвер нормализует
  MIME (нижний регистр, отбрасывание параметров после `;`) перед сравнением с whitelist; учтено в
  таблице правок, whitelist-блоке, Итоге и тестах.
- **Принято [риск] — macro-enabled форматы.** Уточнена формулировка: macro-enabled
  `docm`/`xlsm`/`pptm` (MIME `…macroEnabled.12`) в whitelist НЕ входят; старый `doc` включён по
  запросу, риск нивелируется тем, что Media контент не исполняет.

codex
План в первом чтении был длиннее вывода, поэтому дочитаю оставшиеся разделы и параллельно открою код. Важный фокус сейчас: не совпадает ли план с реальными именами методов, проверками и тестами.
exec
/bin/zsh -lc "sed -n '120,360p' docs/plans/2026-06-22_17-35_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
CompleteMediaUpload
  headObject подтверждает размер
  assertPlanValid(type=Document): все три списка плана (image/video/audio) ДОЛЖНЫ быть пусты,
                 иначе 422 conversion_plan_type_mismatch
  media = uploaded, MediaUploaded(plan=пустой) в outbox

outbox:relay → ProcessMediaJob → ProcessMedia
  match(type): Document ⇒ конверсий нет ([])
  persistReady: copyObject(uploads/... → documents/<shard>/<key>/source.<ext>),
                media → ready (storage = public|private по visibility)

GetMediaUrl
  public: прямой URL; private: presignGet  (без изменений)
```

## Контракты реализации

### Данные и БД

`Не затрагивается`. Колонка `media.type` уже хранит значение `document` (enum `MediaType`
объявлен с этим case-ом, Entity и typecast его уже поддерживают). Миграций, новых таблиц,
полей, индексов нет. Старые данные совместимы: документов в БД ещё не было, потому что загрузка
отклонялась раньше записи.

### API и внешние контракты

`Не затрагивается` в смысле внешних HTTP-контрактов: у модуля Media нет собственных
HTTP-роутов, контроллеров, Filter и Response-классов (см. «Контекст»). Затрагиваемые
`RequestMediaUpload`/`CompleteMediaUpload`/`GetMediaUrl` — внутренние Application-сценарии
(Command/Query + Handler) для других модулей; их сигнатуры и DTO не меняются. Новых ошибок,
status code, webhook'ов, очередей, outbox-сообщений нет.

Поведенческое расширение (в рамках существующих Application-сценариев):

```text
RequestMediaUpload  — теперь резолвит whitelist MIME документов в Document вместо 422.
                      Конкретный потребитель примет документ, только если его MediaUploadSpec
                      .allowedMimeTypes включает нужный MIME (потребитель сужает набор; правка
                      потребителя — вне рамок модуля Media).
CompleteMediaUpload — для документа ожидает пустой план конверсий; непустой план ⇒ 422
                      app.media.conversion_plan_type_mismatch (ключ уже есть в locale).
ProcessMedia        — для документа перекладывает оригинал в documents/... без конверсий.
unsupported_file_type остаётся для реально неизвестных MIME (например font/woff2).
```

Новых ключей локали не требуется (`conversion_plan_type_mismatch`, `unsupported_file_type` уже
есть в `app/locale/ru/media.php` и `app/locale/en/media.php`).

## Фазы выполнения

### 1. Классификация документа в `MediaTypeResolver`

Цель: резолвер распознаёт документ по whitelist MIME раньше префиксных правил и устойчив к
регистру и параметрам MIME.

Что сделать:
- Добавить в `MediaTypeResolver` приватную типизированную константу `DOCUMENT_MIME_TYPES`
  (`@var list<string>`) с нормализованными MIME из whitelist research (все в нижнем регистре):
  `application/pdf`, `application/msword`,
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `text/plain`,
  `application/rtf`, `text/rtf`, `application/vnd.oasis.opendocument.text`,
  `application/vnd.oasis.opendocument.spreadsheet`,
  `application/vnd.oasis.opendocument.presentation`, `application/vnd.ms-excel`,
  `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
  `application/vnd.ms-powerpoint`,
  `application/vnd.openxmlformats-officedocument.presentationml.presentation`,
  `application/epub+zip`, `application/x-fictionbook+xml`, `image/vnd.djvu`, `text/csv`,
  `text/markdown`. Рядом с константой — короткий комментарий, что macro-enabled форматы
  (MIME с суффиксом `…macroEnabled.12`) намеренно НЕ включены (активное содержимое).
- Добавить приватный метод нормализации: нижний регистр + отбросить часть после `;` + `trim`.
- В `resolve()`: вычислить нормализованный MIME, **первым** проверить членство в
  `DOCUMENT_MIME_TYPES` через `\in_array(needle: $normalized, haystack: self::DOCUMENT_MIME_TYPES,
  strict: true)` → `MediaType::Document`; затем существующие три префиксные проверки `str_starts_with`
  **на исходном `$value`** (поведение image/video/audio не меняется); затем существующий
  `throw 422 unsupported_file_type`.
- Обновить классовый докстринг резолвера (строки 11-15): сейчас он утверждает, что документы
  отклоняются и что `MediaPath` допускает только `uploads|images|videos|audios` — после правки
  оба утверждения становятся ложными. Привести формулировку в соответствие (поддержан whitelist
  MIME документов; `MediaPath` допускает и префикс `documents`).

Результат: `resolve('application/pdf') === Document`, `resolve('image/vnd.djvu') === Document`
(не Image), `resolve('text/markdown;charset=utf-8') === Document`, `resolve('image/jpeg') ===
Image`, `resolve('image/png') === Image` (прочий image/* не перехвачен whitelist),
`resolve('font/woff2')` → `ValidationException`.

Критерий готовности докстринга: в докблоке резолвера нет фразы про «документы отклоняются» и про
«только uploads|images|videos|audios»; есть упоминание whitelist документов и префикса
`documents`.

Сценарии тестирования (после фазы) — файл `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php`:
- Позитивные кейсы документов: `application/pdf`, `application/msword`, `text/csv`,
  `text/markdown` → `Document`.
- DJVU `image/vnd.djvu` → `Document`, а НЕ `Image` (порядок проверок).
- Нормализация: `text/markdown;charset=utf-8` → `Document`; `APPLICATION/PDF` и `TEXT/CSV`
  (верхний регистр) → `Document`.
- `image/png` → `Image` (whitelist не перехватывает прочие image/* — кроме djvu).
- `unsupportedMimeTypeProvider` (строки 37-40): убрать ставшие поддержанными `application/pdf` и
  `text/plain`; оставить/добавить реально неподдержанные `font/woff2`, `application/zip`,
  `application/octet-stream` и macro-enabled
  `application/vnd.ms-word.document.macroEnabled.12` (подтверждает исключение макросов).

Проверка: `make test` (новые кейсы зелёные), `make phpstan`.

### 2. Префикс `documents` в `MediaPath`

Цель: путь готового оригинала документа валиден и строится с префиксом `documents`.

Что сделать:
- В `MediaPath::assertValid()` (строка 154) расширить регулярку префиксов:
  `(uploads|images|videos|audios)` → `(uploads|images|videos|audios|documents)`. Проверка
  shard↔uuid (строки 162-164) общая для всех префиксов и не меняется.
- В `MediaPath::originalReady()` (строки 79-86) в `match($type)` заменить ветку
  `MediaType::Document => throw ...` на `MediaType::Document => 'documents'`. `match` остаётся
  исчерпывающим без `default`.
- `originalUpload()` не трогаем.

Результат: `MediaPath::originalReady(type: Document, extension: 'pdf')` →
`documents/<shard>/<key>/source.pdf`; `MediaPath::fromString('documents/aa/<uuid>/source.pdf')`
валиден; пути image/video/audio и uploads — без изменений.

Сценарии тестирования (после фазы) — файл
`tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php`:
- В `testMediaPathFactoriesBuildValidPaths` (≈строка 78) добавить
  `originalReady(Document, 'pdf')` → ожидаемая строка с префиксом `documents/`.
- Перевернуть существующий негатив на строках 132-141 (где `originalReady(Document)` ожидает
  `InvalidDomainValueException`) в позитивный кейс построения пути `documents/...`.
- Добавить валидный `MediaPath::fromString('documents/<shard>/<uuid>/source.pdf')` с корректным
  shard и один негатив: `documents/<bad-shard>/<uuid>/source.pdf` (shard не равен первым двум hex
  uuid) → `InvalidDomainValueException` (покрывает новую ветку regex на позитив и негатив).

Проверка: `make test`, `make phpstan`.

### 3. Пустой набор конверсий для документа в `ProcessMediaHandler`

Цель: документ перекладывается из staging в постоянное хранилище без конверсий и переходит в
ready.

Что сделать:
- В `ProcessMediaHandler::handle()` `match($media->type)` (строка 90) заменить
  `MediaType::Document => throw new InvalidDomainValueException(...)` на
  `MediaType::Document => []` (пустой `list` конверсий).
- Дальнейший код не меняется: существующий `persistReady()` вызовет
  `MediaPath::originalReady(type: Document, ...)` (теперь возвращает `documents/...`),
  сделает `copyObject` оригинала и `markReadyMovedTo`. Для пустого набора `getObjectContents`/
  `putObject` не вызываются (как в существующем кейсе пустого плана у image).

Результат: документ-медиа после `handle()` → `isReady() === true`, `storage` = public|private
по visibility, конверсий 0, ready-путь начинается с `documents/`.

Сценарии тестирования (после фазы) — файл
`tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php`:
- Перевернуть существующий `testRejectsDocumentType` (строки 253-270) и **переименовать** в
  `testProcessesDocumentByMovingOriginalWithoutConversions` (имя теста должно описывать
  позитивный сценарий). Мок `MediaFileServiceContract`: `copyObject` ровно один раз
  (`expects(self::once())`), `getObjectContents`/`putObject` — `never`. После `handle()`:
  `media->isReady()`, конверсий 0, и ready-путь медиа начинается с `documents/` (проверка через
  состояние media — отличает документ от перекладки image). Этот тест НЕ дублирует существующий
  `testProcessesMediaWithoutConversionsMovesOriginalOnly` (там image+пустой план): новый кейс
  проверяет именно тип Document и префикс `documents/`.

Проверка: `make test`, `make phpstan`.

### 4. Валидация пустого плана документа в `CompleteMediaUploadHandler` + загрузка документа end-to-end

Цель: подтверждение загрузки документа проходит только с пустым планом конверсий; непустой план
отклоняется как кросс-тип 422; документ проходит весь путь до ready.

Что сделать:
- В `CompleteMediaUploadHandler::assertPlanValid()` `match($type)` (строка 123) заменить
  `MediaType::Document => throw new ValidationException('unsupported_file_type'...)` на
  `MediaType::Document => $this->assertDocumentPlan($plan)`.
- Добавить приватный метод `assertDocumentPlan(MediaConversionPlan $plan): void`: если
  `$plan->image !== [] || $plan->video !== [] || $plan->audio !== []` → бросить
  `ValidationException('app.media.conversion_plan_type_mismatch')`. (Стиль как у существующих
  `assertImagePlan`/`assertVideoPlan`/`assertAudioPlan`; пустой план для документа корректен,
  отдельный guard на «ноль элементов» не нужен.)
- `match` остаётся исчерпывающим без `default`.

Результат: документ с пустым планом → `MediaStatus::Uploaded`, `MediaUploaded` уходит в outbox;
документ с любым непустым списком конверсий → 422 `conversion_plan_type_mismatch`.

Сценарии тестирования (после фазы):

`tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php`:
- Перевернуть существующий `testRejectsDocumentMedia` (строки 345-359) и **переименовать** в
  `testCompletesDocumentUploadWithEmptyPlan`: документ + `emptyPlan()` + `fileServiceWithHead(...)`
  (мок `headObject` обязателен, иначе `assertObjectUploaded` упадёт) → `status === Uploaded`,
  `MediaUploaded` добавлено в outbox.
- Добавить новый негатив `testRejectsNonEmptyPlanForDocument`: документ + непустой план (например
  `imagePlan(imageConversionSpec())`) → `ValidationException`. Срабатывает только проверка
  `assertDocumentPlan`, поэтому ожидания класса `ValidationException` достаточно (как в остальных
  кейсах этого теста).

`tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php`:
- Позитив `testRequestsDocumentUpload`: `spec` с `allowedMimeTypes`, включающим
  `application/pdf`, `fileMeta` (`document.pdf`, `application/pdf`) → создаётся media с типом
  `Document`, статус `WaitingUpload`, staging-путь начинается с `uploads/` (тест читает
  `media->type` и `media->path`).
- Негатив `testRejectsDocumentMimeOutsideSpec`: документный MIME, который резолвер поддерживает,
  но которого нет в `spec.allowedMimeTypes` → `ValidationException` (`mime_not_allowed`). Это
  показывает ключевой тезис плана: потребитель сужает набор, а не расширяет.

`tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` (end-to-end через MinIO):
- Добавить документный кейс: `RequestMediaUpload(application/pdf)` → PUT байтов в staging →
  `CompleteMediaUpload(emptyPlan)` → `outbox:relay`/`ProcessMedia` → media `ready`. Проверить, что
  реальный объект лежит по ready-пути `documents/<shard>/<key>/source.pdf` (через `headObject` по
  этому пути) и конверсий нет. Это единственный тест, реально исполняющий `persistReady`→
  `copyObject`→`MediaPath::assertValid('documents/...')` без моков файлового сервиса.

Проверка: `make test`, `make phpstan`.

## Тесты

Стратегия: **after_each_phase**. После каждой фазы добавляются/обновляются тесты именно для
правки этой фазы и прогоняется `make test` + `make phpstan`, прежде чем переходить к следующей
фазе. Затрагиваемые тестовые файлы:

- `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php` (фаза 1)
- `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` (фаза 2)
- `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (фаза 3)
- `tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php` (фаза 4)
- `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php` (фаза 4)
- `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` (фаза 4, end-to-end)

Требование проекта — 100% покрытие. У модуля Media нет HTTP-роутов, поэтому покрытие
обеспечивают feature-тесты Handler'ов плюс end-to-end Flow-тест, который реально гоняет
документ через MinIO. Все перевёрнутые негативы перестают проверять отклонение и начинают
проверять успешный документный путь; имена таких тестов переименовываются под позитивный
сценарий.

Тесты запускаются только через Docker: `make test` (часть тестов ходит в `minio:9000`, доступный
лишь внутри Docker-сети). PHPStan — `make phpstan`.

## Логирование

Стратегия: **debug_precise**. Документная ветка должна быть прослеживаема на каждом переходе на
уровне DEBUG, без новых лог-строк там, где этого не требует дизайн.

- `MediaTypeResolver` и `MediaPath` остаются без логгера: это чистый сервис и доменный VO,
  ввод логгера в них нарушил бы их природу (правило «Логирование: DEBUG по умолчанию» относится
  к бизнес-логике в Handler-ах, а не к чистым VO/резолверам).

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Service/MediaTypeResolver.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;

/**
 * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны image/*, video/* и audio/*;
 * прочие типы (документы и т.п.) вне области пайплайна и отклоняются как ожидаемая клиентская
 * ошибка 422 (MediaPath допускает только префиксы uploads|images|videos|audios).
 */
final readonly class MediaTypeResolver
{
    public function resolve(MediaMimeType $mimeType): MediaType
    {
        $value = $mimeType->value();

        if (\str_starts_with(haystack: $value, needle: 'image/')) {
            return MediaType::Image;
        }

        if (\str_starts_with(haystack: $value, needle: 'video/')) {
            return MediaType::Video;
        }

        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
            return MediaType::Audio;
        }

        throw new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => $value],
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Domain/ValueObject/MediaPath.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

final readonly class MediaPath implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
    {
        return self::fromString(
            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function imageConversion(
        MediaStorageKey $storageKey,
        MediaImageConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'images/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function videoConversion(
        MediaStorageKey $storageKey,
        MediaVideoConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'videos/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function audioConversion(
        MediaStorageKey $storageKey,
        MediaAudioConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'audios/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
    {
        $prefix = match ($type) {
            MediaType::Image => 'images',
            MediaType::Video => 'videos',
            MediaType::Audio => 'audios',
            MediaType::Document => throw new InvalidDomainValueException(
                'Перекладка готового оригинала для документов не поддержана.',
            ),
        };

        return self::fromString(
            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);
        self::assertValid($value);

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Расширение файла из последнего сегмента пути (без точки). Используется для построения
     * путей конверсий и готового оригинала с тем же форматом, что у загруженного файла.
     */
    public function extension(): string
    {
        $segments = \explode(separator: '/', string: $this->value);
        $fileName = (string) \end($segments);
        $dotPosition = \strrpos(haystack: $fileName, needle: '.');

        return $dotPosition === false ? '' : \substr(string: $fileName, offset: $dotPosition + 1);
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

    private static function sanitizeExtension(string $extension): string
    {
        $safeExtension = \strtolower(\trim($extension));

        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
        }

        return $safeExtension;
    }

    private static function assertValid(string $value): void
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Путь файла слишком длинный.');
        }

        $matches = [];
        if (\preg_match(
            pattern: '/^(uploads|images|videos|audios)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
            subject: $value,
            matches: $matches,
        ) !== 1) {
            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
        }

        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaMimeType implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('MIME type файла имеет неверную длину.');
        }

        return new self(value: $value);
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
}

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
 * пересоздаёт их без конфликта по unique (media_id, type) (процессоры перезаписывают объекты
 * по детерминированным путям).
 */
final readonly class ProcessMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaImageProcessorContract $mediaImageProcessor,
        private MediaVideoProcessorContract $mediaVideoProcessor,
        private MediaAudioProcessorContract $mediaAudioProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if ($media->isReady()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return;
        }

        $this->logger->debug(message: 'Начата обработка медиа.', context: [
            'mediaId' => $media->id->value(),
            'type' => $media->type->value,
        ]);

        $targetStorage = $this->targetStorage($media->visibility);
        $extension = $media->path->extension();

        $conversions = match ($media->type) {
            MediaType::Image => $this->buildImageConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                extension: $extension,
            ),
            MediaType::Video => $this->buildVideoConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Audio => $this->buildAudioConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
        };

        $this->persistReady(
            media: $media,
            conversions: $conversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     */
    private function persistReady(
        Media $media,
        array $conversions,
        MediaStorage $targetStorage,
        string $extension,
    ): void {
        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
        $this->mediaFileService->copyObject(
            fromStorage: $media->storage,
            fromPath: $media->path,
            toStorage: $targetStorage,
            toPath: $targetPath,
        );
        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);

        $this->entityManager->persist($media);
        foreach ($conversions as $conversion) {
            $this->entityManager->persist($conversion);
        }
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа готово.', context: [
            'mediaId' => $media->id->value(),
            'targetStorage' => $targetStorage->value,
            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
        ]);
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @param class-string $conversionClass
     */
    private function countByClass(array $conversions, string $conversionClass): int
    {
        return Collection::make($conversions)
            ->filter(static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
                => $conversion instanceof $conversionClass)
            ->count();
    }

    /**
     * @return list<MediaImageConversion>
     */
    private function buildImageConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($plan->image === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($plan->image as $spec) {
            $conversionResult = $this->mediaImageProcessor->resize(
                originalContents: $originalContents,
                width: MediaPixelDimension::fromInt($spec->width),
                height: MediaPixelDimension::fromInt($spec->height),
                targetMimeType: $media->mimeType,
            );
            $conversionPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $spec->type,
                extension: $extension,
            );
            $this->mediaFileService->putObject(
                storage: $targetStorage,
                path: $conversionPath,
                contents: $conversionResult->contents,
                mimeType: $conversionResult->mimeType,
            );

            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
                'type' => $spec->type->value,
                'path' => $conversionPath->value(),
                'size' => $conversionResult->size->value(),
            ]);

            // width/height берём из результата процессора (размер реально записанного объекта),
            // а не из spec — устойчиво к смене режима ресайза. См. README.
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $conversionPath,
                mimeType: $conversionResult->mimeType,
                size: $conversionResult->size,
                width: $conversionResult->width,
                height: $conversionResult->height,
            );
        }

        return $conversions;
    }

    /**
     * @return list<MediaVideoConversion|MediaImageConversion>
     */
    private function buildVideoConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->video as $spec) {
            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'mp4');
            $posterPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            );

            $result = $this->mediaVideoProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
                posterPath: $posterPath,
            );

            $conversions[] = MediaVideoConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\CompleteMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

final readonly class CompleteMediaUploadHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaFileServiceContract $mediaFileService,
        private OutboxEventStoreContract $outboxEventStore,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteMediaUploadCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if ($media->status !== MediaStatus::WaitingUpload) {
            throw new ValidationException('app.media.upload_not_pending');
        }

        $this->assertPlanValid(plan: $command->plan, type: $media->type);

        if ($command->parts !== null) {
            $this->completeMultipartUpload(media: $media, parts: $command->parts);
        }

        $this->assertObjectUploaded($media);

        $media->markUploaded();
        $this->outboxEventStore->add(new MediaUploaded(
            mediaId: $media->id->value(),
            plan: $command->plan,
        ));
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'imageConversions' => \count($command->plan->image),
            'videoConversions' => \count($command->plan->video),
            'audioConversions' => \count($command->plan->audio),
        ]);

        return MediaResult::fromEntity($media);
    }

    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
            ?? throw new ValidationException('app.media.multipart_upload_not_found');

        $multipartUpload->replaceParts($parts);
        $this->entityManager->persist($multipartUpload);
        $this->mediaFileService->completeMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
            parts: $parts,
        );
    }

    private function assertObjectUploaded(Media $media): void
    {
        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);

        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
            throw new ValidationException('app.media.uploaded_object_mismatch');
        }
    }

    /**
     * Валидирует ТОЛЬКО список, относящийся к типу медиа; список «не своего» типа должен быть
     * пустым (кросс-тип → 422). Диапазоны проверяются через контракт VO ::supports() на
     * Application-границе, чтобы невалидная спека не прошла подтверждение и не упала асинхронно в
     * ProcessMedia (после S3-записей и unique-конфликта).
     */
    private function assertPlanValid(MediaConversionPlan $plan, MediaType $type): void
    {
        match ($type) {
            MediaType::Image => $this->assertImagePlan($plan),
            MediaType::Video => $this->assertVideoPlan($plan),
            MediaType::Audio => $this->assertAudioPlan($plan),
            MediaType::Document => throw new ValidationException(
                translationKey: 'app.media.unsupported_file_type',
                translationParameters: ['type' => $type->value],
            ),
        };
    }

    private function assertImagePlan(MediaConversionPlan $plan): void
    {
        if ($plan->video !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        $types = Collection::make($plan->image)->map(
            static fn(MediaImageConversionSpec $spec): string => $spec->type->value,
        );
        if ($types->count() !== $types->unique()->count()) {
            throw new ValidationException('app.media.conversion_duplicate_type');
        }

        foreach ($plan->image as $spec) {
            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
                throw new ValidationException('app.media.conversion_dimensions_out_of_range');
            }
        }
    }

    private function assertVideoPlan(MediaConversionPlan $plan): void
    {
        if ($plan->image !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->video) !== 1) {
            throw new ValidationException('app.media.video_conversion_profile_required');
        }

        $spec = $plan->video[0];
        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
        }

        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }
    }

    private function assertAudioPlan(MediaConversionPlan $plan): void
    {
        if ($plan->image !== [] || $plan->video !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->audio) !== 1) {
            throw new ValidationException('app.media.audio_conversion_profile_required');
        }

        $spec = $plan->audio[0];
        if (!MediaBitrate::supports($spec->bitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }

        if (!MediaSampleRate::supports($spec->sampleRate)) {
            throw new ValidationException('app.media.conversion_sample_rate_out_of_range');
        }

        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
            throw new ValidationException('app.media.conversion_waveform_peaks_out_of_range');
        }
    }
}

exec
/bin/zsh -lc "sed -n '360,760p' docs/plans/2026-06-22_17-35_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
  к бизнес-логике в Handler-ах, а не к чистым VO/резолверам).
- Прослеживаемость документа обеспечивают уже существующие DEBUG-логи на границе Handler-ов, и
  они уже включают нужные поля:
  - `RequestMediaUploadHandler` — «Запрошена загрузка медиа.» с `mimeType` (видно, что принят
    документный MIME).
  - `ProcessMediaHandler` — «Начата обработка медиа.» с `type` (= `document`) и «Медиа готово.» со
    счётчиками конверсий (для документа все три = 0).
  - `CompleteMediaUploadHandler` — «Загрузка медиа подтверждена.» со счётчиками плана (для
    документа все 0).
- Новые DEBUG-строки не добавляются: каждый переход документного пути уже логируется
  существующими сообщениями, и они корректно отражают `type=document`/нулевые конверсии.

## Документация и эксплуатация

- Миграций, env-переменных, новых конфигов и зависимостей нет — изменений в `.env`/runbook не
  требуется.
- OpenAPI-генерация не нужна: у модуля Media нет контроллеров/Filter/Response, whitelist MIME
  живёт только в коде резолвера и в сгенерированное описание не попадает.
- Эксплуатационное замечание для потребителей модуля: чтобы конкретная фича (например вложения
  к постам) реально принимала документы, её `MediaUploadSpec.allowedMimeTypes` должен включать
  нужные документные MIME — это правка на стороне потребителя, вне модуля Media. Если у такого
  потребителя есть HTTP-контракт/OpenAPI, прогон `php app.php openapi:generate` выполняется в его
  задаче, а не здесь.
- Осиротевшие staging-объекты документа чистятся существующей staging-expiry задачей, как и
  другие типы (без изменений).

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:**
  - End-to-end документный кейс в `MediaProcessingFlowTest` (фаза 4): реальная перекладка в
    `documents/...` через MinIO, а не только моки `copyObject`.
  - Негатив `mime_not_allowed` в `RequestMediaUploadHandlerTest`: документный MIME вне
    `spec.allowedMimeTypes` отклоняется — доказывает тезис «потребитель сужает набор».
  - Позитивный кейс `image/png → Image` и регистровые кейсы (`APPLICATION/PDF`, `TEXT/CSV`) в
    `MediaTypeResolverTest`; явное исключение macro-enabled через
    `application/vnd.ms-word.document.macroEnabled.12`.
  - Конкретный негатив по shard для пути `documents/...` в `MediaValueObjectTest`.
  - Критерий готовности для правки докстринга резолвера.
- **~ Изменено:**
  - Раздел «API и внешние контракты» и «Тесты»: исправлена фактическая ошибка — у модуля Media
    нет HTTP-роутов/контроллеров; формулировки «маршруты», «интеграционные тесты роутов»
    заменены на «Application-сценарии (CQRS Handler'ы)» и «feature-тесты Handler'ов».
  - Перевёрнутые негативы переименованы под позитивный сценарий
    (`testProcessesDocumentByMovingOriginalWithoutConversions`,
    `testCompletesDocumentUploadWithEmptyPlan`).
  - Явно зафиксировано: нормализация MIME применяется только к сравнению с whitelist, префиксные
    проверки остаются на исходном `$value` (поведение image/video/audio не меняется).
  - Уточнены обязательные детали тестов: мок `headObject` в позитивном Complete-кейсе; проверка
    префикса `documents/` через состояние media в Process-кейсе.
- **− Убрано:**
  - Шаг `php app.php openapi:generate` как обязательный для этой задачи — неприменим к Media
    (нет HTTP-поверхности); оставлено лишь как заметка для задачи потребителя.
- **Отклонено:**
  - Добавление `text/x-rtf` (sonnet) и `image/svg+xml` (opus) в whitelist — набор форматов
    зафиксирован пользователем в research; прочие MIME-варианты считаются неподдержанными до
    явного добавления отдельной задачей.
  - Нормализация префиксных проверок image/video/audio (opus, «для консистентности») — это
    изменило бы существующее поведение этих типов вне рамок задачи; research требует нормализацию
    только перед сравнением с whitelist.

exec
/bin/zsh -lc "sed -n '241,520p' app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
                width: $result->width,
                height: $result->height,
                duration: $result->duration,
                bitrate: $result->bitrate,
            );
            // Постер видео — MediaImageConversion type=Poster размером транскода (width/height).
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: MediaImageConversionType::Poster,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $posterPath,
                mimeType: $result->posterMimeType,
                size: $result->posterSize,
                width: $result->width,
                height: $result->height,
            );

            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }

        return $conversions;
    }

    /**
     * @return list<MediaAudioConversion>
     */
    private function buildAudioConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->audio as $spec) {
            $normalizedPath = MediaPath::audioConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'm4a');

            $result = $this->mediaAudioProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
            );

            $conversions[] = MediaAudioConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                duration: $result->duration,
                bitrate: $result->bitrate,
                sampleRate: $result->sampleRate,
                waveform: $result->waveform,
            );

            $this->logger->debug(message: 'Создана конверсия аудио с волной.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }

        return $conversions;
    }

    private function targetStorage(MediaVisibility $visibility): MediaStorage
    {
        return match ($visibility) {
            MediaVisibility::Public => MediaStorage::Public,
            MediaVisibility::Private => MediaStorage::Private,
        };
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class RequestMediaUploadHandler
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaTypeResolver $mediaTypeResolver,
        private MediaConfig $mediaConfig,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
    {
        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
        $this->assertUploadAllowed(spec: $command->spec, fileMeta: $command->fileMeta);

        $storageKey = MediaStorageKey::generate();
        $media = Media::create(
            storageKey: $storageKey,
            type: $mediaType,
            visibility: $command->spec->visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $this->extension($command->fileMeta)),
            mimeType: $command->fileMeta->mimeType,
            size: $command->fileMeta->size,
            uploadedById: UserId::fromString($command->userId),
            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
        );

        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
        $preparedUpload = $this->isMultipart($command->fileMeta->size)
            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);

        $this->entityManager->run();

        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'uploadMode' => $preparedUpload->uploadMode->value,
            'size' => $command->fileMeta->size->value(),
            'mimeType' => $command->fileMeta->mimeType->value(),
            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
        ]);

        return $preparedUpload;
    }

    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $putUrl = $this->mediaFileService->presignPut(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
            expiresAt: $presignedExpiresAt,
        );
        $this->entityManager->persist($media);

        return RequestMediaUploadResult::single(
            mediaId: $media->id->value(),
            putUrl: $putUrl,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
        $partsCount = MediaMultipartPartsCount::fromInt($this->partsCount(size: $media->size, partSize: $partSize));
        $uploadId = $this->mediaFileService->createMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
        );
        $parts = $this->mediaFileService->presignUploadParts(
            storage: $media->storage,
            path: $media->path,
            uploadId: $uploadId,
            partsCount: $partsCount,
            expiresAt: $presignedExpiresAt,
        );

        $this->entityManager->persist($media);
        $this->entityManager->persist(MediaMultipartUpload::create(
            media: $media,
            uploadId: $uploadId,
            partsCount: $partsCount,
            partSize: $partSize,
            fileSize: $media->size,
        ));

        return RequestMediaUploadResult::multipart(
            mediaId: $media->id->value(),
            uploadId: $uploadId->value(),
            parts: $parts,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
    {
        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
            throw new ValidationException(
                translationKey: 'app.media.mime_not_allowed',
                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
            );
        }

        if ($fileMeta->size->value() > $spec->maxSize->value()) {
            throw new ValidationException('app.media.file_size_exceeded');
        }
    }

    private function extension(MediaFileMeta $fileMeta): string
    {
        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new ValidationException('app.media.file_name_without_extension');
        }

        return $extension;
    }

    private function isMultipart(MediaFileSize $size): bool
    {
        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
    }

    private function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): int
    {
        return (int) \ceil($size->value() / $partSize->value());
    }

    private function expiresIn(int $seconds): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' app/src/Modules/Media/Domain/Enum/MediaType.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
}

codex
Код подтверждает основную схему плана: заглушки действительно в перечисленных местах. Теперь проверяю тесты и вспомогательные фабрики, потому что риски чаще всего прячутся в том, как реально создаются Media и планы конверсий.
exec
/bin/zsh -lc "rg --files tests | rg 'Media(TypeResolver|ValueObject|ProcessMediaHandler|CompleteMediaUploadHandler|RequestMediaUploadHandler|MediaProcessingFlow)'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc 'rg -n "Document|document|MediaType::Document|unsupported|emptyPlan|imagePlan|conversion_plan|mime_not_allowed|documents" tests/Unit/Modules/Media tests/Feature/Modules/Media' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "rg -n \"GetMediaUrl|originalReady|source\\.|documents|media->path|path->value|headObject\" app/src/Modules/Media tests/Feature/Modules/Media tests/Unit/Modules/Media" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php

 succeeded in 0ms:
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:138:            type: MediaType::Document,
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:21:        self::assertSame(['image', 'video', 'audio', 'document'], $this->values(MediaType::cases()));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:26:    #[DataProvider('unsupportedMimeTypeProvider')]
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:37:    public static function unsupportedMimeTypeProvider(): array
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:56:                plan: $this->imagePlan($this->imageConversionSpec()),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:82:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:164:            plan: $this->imagePlan($this->imageConversionSpec(), $this->imageConversionSpec()),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:180:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:308:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:345:    public function testRejectsDocumentMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:348:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:356:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:368:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:383:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:400:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:417:            plan: $this->imagePlan($this->imageConversionSpec(width: $width, height: $height)),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:446:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:465:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:481:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:49:            plan: $this->imagePlan($this->imageConversionSpec()),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:71:            plan: $this->imagePlan($this->imageConversionSpec(width: 100, height: 100)),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:91:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:247:            plan: $this->imagePlan($this->imageConversionSpec()),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:253:    public function testRejectsDocumentType(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:257:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:268:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:278:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:291:            plan: $this->emptyPlan(),
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:102:    protected function emptyPlan(): MediaConversionPlan
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:107:    protected function imagePlan(MediaImageConversionSpec ...$specs): MediaConversionPlan
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:42:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), plan: $this->emptyPlan()));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:78:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), plan: $this->emptyPlan()));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:110:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $missingMediaId, plan: $this->emptyPlan()));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:58:        $this->completeUpload($media, $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:86:            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:106:            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:213:            $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),

 succeeded in 0ms:
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:69:            'uploads/11/11111111-1111-4111-8111-111111111111/source.jpg',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:75:        MediaPath::fromString('uploads/aa/11111111-1111-4111-8111-111111111111/source.jpg');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:91:            'images/ab/ab111111-1111-4111-8111-111111111111/source.png',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:92:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Image, extension: 'png'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:95:            'videos/ab/ab111111-1111-4111-8111-111111111111/source.mp4',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:96:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Video, extension: 'mp4'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:99:            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:100:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:119:            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:120:            MediaPath::fromString('audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a')->value(),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:136:        MediaPath::originalReady(
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:226:        MediaProcessingError::fromString('Ошибка uploads/11/11111111-1111-4111-8111-111111111111/source.jpg');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:343:            'uploads/11/11111111-1111-4111-8111-111111111111/source.jpg',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:344:            MediaPath::fromString('uploads/11/11111111-1111-4111-8111-111111111111/source.jpg')->jsonSerialize(),
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:137:        self::assertTrue($targetPath->equals($media->path));
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:142:        self::assertTrue($targetPath->equals($media->path));
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:199:        return MediaPath::originalReady(
app/src/Modules/Media/Domain/Entity/Media.php:127:        $media->path = $path;
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:50:        $head = $fileService->headObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:94:        $head = $fileService->headObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:163:        self::assertNotNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:166:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:170:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:192:        self::assertNull($this->fileService()->headObject(MediaStorage::Upload, $this->uploadPath()));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:242:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:83:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:84:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:192:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:144:            if ($exception->getAwsErrorCode() === 'NoSuchUpload' && $this->headObject(storage: $storage, path: $path) !== null) {
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:174:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:177:            $contentLength = $this->client($storage)->headObject([
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:354:            return $path->value();
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:357:        return \sprintf('%s/%s', \rtrim(string: $prefix, characters: '/'), $path->value());
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:76:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:80:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:81:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:123:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:128:        self::assertSame($readyPath->value(), $readyMedia->path->value());
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:129:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:130:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:163:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:169:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:170:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:199:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'wav');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:203:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:248:            path: $media->path,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:252:        $this->track(MediaStorage::Upload, $media->path);
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:43:        $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:59:        $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:75:        $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:92:            'headObject' => static fn(): Result => new Result(['ContentLength' => 'not-a-number']),
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:97:        $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:115:            'headObject' => static fn(): Result => new Result(['ContentLength' => 1024]),
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:130:        self::assertNotNull($fileService->headObject(MediaStorage::Upload, $this->path()));
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:137:            // Объекта нет: headObject отвечает NoSuchKey -> isNotFound -> null -> идемпотентность не подтверждена.
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:138:            'headObject' => static fn(): Result => throw self::s3Exception('NoSuchKey'),
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:282:            'headObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:287:        $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:367:                'headObject' => static fn(): Result => new Result(['ContentLength' => 42]),
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:371:        $objectHead = $fileService->headObject(MediaStorage::Upload, $this->path());
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:90:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:8:use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:9:use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:32:final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:43:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:64:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:92:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:124:                $capturedPath = $path->value();
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:131:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:138:        self::assertSame($conversion->path->value(), $capturedPath);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:159:                $capturedPath = $path->value();
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:165:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:172:        self::assertSame($conversion->path->value(), $capturedPath);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:186:                $capturedPath = $path->value();
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:192:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:199:        self::assertSame($conversion->path->value(), $capturedPath);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:209:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:224:            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:232:            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:235:    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:237:        return new GetMediaUrlHandler(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:253:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:237:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:54:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:100:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:83:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
app/src/Modules/Media/README.md:25:| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (`conversionType` — union image/video/audio-enum или null; для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — прямой URL без срока; срок применяется только для private) |
app/src/Modules/Media/README.md:61:3. CompleteMediaUpload  -> headObject подтверждает объект и размер; media = uploaded;
app/src/Modules/Media/README.md:68:6. GetMediaUrl: public -> прямой URL (media-public, anonymous read); private -> presignGet
app/src/Modules/Media/README.md:100:  (`completeMultipartUpload`, `headObject`) выполняются под открытой транзакцией БД. Это
app/src/Modules/Media/README.md:102:  `headObject`), а транзакция короткая. Если появятся проблемы с длительными транзакциями (блокировки,
app/src/Modules/Media/README.md:119:  чтобы `GetMediaUrl` резолвил их единообразно и не было утечки private-медиа.
app/src/Modules/Media/README.md:153:  `GetMediaUrlQuery.presignedTtlSeconds` (скачивание). Ключи `MEDIA_*` — в `.env.sample` и
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:87:                $deletedPaths[] = $path->value();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:97:        self::assertContains($media->path->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:113:                $deletedPaths[] = $path->value();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:123:        self::assertContains($media->path->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:139:                $deletedPaths[] = $path->value();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:149:        self::assertContains($media->path->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:215:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:244:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:273:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:21: * MediaUploadSpec (загрузка) и GetMediaUrlQuery (скачивание), поэтому presign-методы принимают expiresAt.
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:59:     * это как успех, если headObject подтверждает собранный объект.
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:80:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:25:            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:77:    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:89:            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:10: * Метаданные объекта в хранилище (результат headObject). null от контракта означает,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:96:            path: $media->path,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:104:        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:44:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:78:            path: $media->path,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:97:            path: $media->path,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:102:            path: $media->path,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:16: * Не бросающий вариант GetMediaUrl для best-effort отображения (например, аватара в профиле):
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:38:                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:48:            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:71:        $extension = $media->path->extension();
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:110:        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:113:            fromPath: $media->path,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:159:        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:226:                sourcePath: $media->path,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:284:                sourcePath: $media->path,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:76:        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:458:        $fileService->method('headObject')->willReturn(null);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:503:        $fileService->method('headObject')->willReturn(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:5:namespace App\Modules\Media\Application\Query\GetMediaUrl;
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:11:final readonly class GetMediaUrlQuery
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:5:namespace App\Modules\Media\Application\Query\GetMediaUrl;
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:26:final readonly class GetMediaUrlHandler
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:36:    public function handle(GetMediaUrlQuery $query): MediaUrlResult
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:60:            path: $media->path,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:55:        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:93:            path: $media->path,

exec
/bin/zsh -lc 'rg -n "class MediaUploadSpec|allowedMimeTypes|MediaMimeTypeCollection|containsMimeType" app/src/Modules/Media tests' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:13:final class MediaMimeTypeCollection extends Collection
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:15:    public function containsMimeType(MediaMimeType $mimeType): bool
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:16:use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:190:            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
app/src/Modules/Media/README.md:38:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:127:        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:7:use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:16:final readonly class MediaUploadSpec
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,

codex
Нашёл, что часть нужных тестов есть, но первый поиск по именам показал только unit-файлы из-за структуры путей. Открываю конкретные feature-тесты и общую базу тестов, чтобы проверить, нет ли пропущенных зависимостей в плане.
exec
/bin/zsh -lc "sed -n '1,360p' tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,240p' tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,360p' tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Application;

use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaTypeResolverTest extends TestCase
{
    public function testResolvesImageVideoAndAudio(): void
    {
        $resolver = new MediaTypeResolver();

        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mpeg')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
    }

    #[DataProvider('unsupportedMimeTypeProvider')]
    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
    {
        $this->expectException(ValidationException::class);

        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
    }

    /**
     * @return list<array{string}>
     */
    public static function unsupportedMimeTypeProvider(): array
    {
        return [['application/pdf'], ['text/plain'], ['font/woff2']];
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Application\Dto\MediaConversionResult;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class ProcessMediaHandlerTest extends MediaApplicationTestCase
{
    public function testProcessesImageMediaWithConversions(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');
        $fileService->expects(self::once())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec()),
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Private, $media->storage);

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);
        self::assertSame(MediaStorage::Private, $conversions->first()->storage);
    }

    public function testStoresConversionDimensionsFromProcessorResult(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');

        $handler = $this->handler(fileService: $fileService, resultWidth: 100, resultHeight: 56);
        $handler->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(width: 100, height: 100)),
        ));

        $conversion = $this->imageConversionRepository()->findByMediaId($media->id)->first();
        self::assertSame(100, $conversion->width->value());
        self::assertSame(56, $conversion->height->value());
    }

    public function testProcessesMediaWithoutConversionsMovesOriginalOnly(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Public, $media->storage);
        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testProcessesVideoMediaIntoNormalizedAndPoster(): void
    {
        $media = $this->uploadedVideoMedia();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::once())->method('copyObject');

        $videoProcessor = $this->createStub(MediaVideoProcessorContract::class);
        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
            normalizedSize: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
            posterMimeType: MediaMimeType::fromString('image/jpeg'),
            posterSize: MediaFileSize::fromInt(512),
        ));

        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($videoConversion);
        self::assertSame(1280, $videoConversion->width->value());
        self::assertSame(2000, $videoConversion->duration->value());

        $poster = $this->imageConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($poster);
        self::assertSame(MediaImageConversionType::Poster, $poster->type);
        self::assertSame(1280, $poster->width->value());
    }

    public function testProcessesAudioMediaIntoNormalizedWithWaveform(): void
    {
        $media = $this->uploadedAudioMedia();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::once())->method('copyObject');

        $audioProcessor = $this->createStub(MediaAudioProcessorContract::class);
        $audioProcessor->method('process')->willReturn(new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
            normalizedSize: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        ));

        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($audioConversion);
        self::assertSame(44_100, $audioConversion->sampleRate->value());
        self::assertSame([0, 64, 128, 255], $audioConversion->waveform->peaks());
    }

    public function testRerunsVideoAfterProcessingFailed(): void
    {
        $media = $this->uploadedVideoMedia();
        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка обработки.'));
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('copyObject');

        $videoProcessor = $this->createStub(MediaVideoProcessorContract::class);
        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
            normalizedSize: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(640),
            height: MediaPixelDimension::fromInt(480),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(500_000),
            posterMimeType: MediaMimeType::fromString('image/jpeg'),
            posterSize: MediaFileSize::fromInt(256),
        ));

        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
        ));

        self::assertTrue($media->isReady());
        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
    }

    public function testRerunsAudioAfterProcessingFailed(): void
    {
        $media = $this->uploadedAudioMedia();
        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка обработки.'));
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('copyObject');

        $audioProcessor = $this->createStub(MediaAudioProcessorContract::class);
        $audioProcessor->method('process')->willReturn(new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
            normalizedSize: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        ));

        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $audioConversions = $this->audioConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $audioConversions);
        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
    }

    public function testIsNoOpWhenMediaAlreadyReady(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $media->markReadyMovedTo(
            MediaStorage::Private,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec()),
        ));

        self::assertTrue($media->isReady());
    }

    public function testRejectsDocumentType(): void
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            type: MediaType::Document,
            extension: 'pdf',
            mimeType: 'application/pdf',
        );
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: UserId::generate()->value(),
            plan: $this->emptyPlan(),
        ));
    }

    public function testRejectsMediaInWaitingUploadStatus(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        int $resultWidth = 100,
        int $resultHeight = 100,
        MediaVideoProcessorContract|null $videoProcessor = null,
        MediaAudioProcessorContract|null $audioProcessor = null,
    ): ProcessMediaHandler {
        $imageProcessor = $this->createStub(MediaImageProcessorContract::class);
        $imageProcessor->method('resize')->willReturn(new MediaConversionResult(
            contents: 'conversion-bytes',
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(128),
            width: MediaPixelDimension::fromInt($resultWidth),
            height: MediaPixelDimension::fromInt($resultHeight),
        ));

        return new ProcessMediaHandler(
            mediaRepository: $this->mediaRepository(),
            mediaFileService: $fileService,
            mediaImageProcessor: $imageProcessor,
            mediaVideoProcessor: $videoProcessor ?? $this->createStub(MediaVideoProcessorContract::class),
            mediaAudioProcessor: $audioProcessor ?? $this->createStub(MediaAudioProcessorContract::class),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }

    private function uploadedMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();

        return $media;
    }

    private function uploadedVideoMedia(): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Video,
            extension: 'mp4',
            mimeType: 'video/mp4',
        );
        $media->markUploaded();

        return $media;
    }

    private function uploadedAudioMedia(): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Audio,
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );
        $media->markUploaded();

        return $media;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\ValueObject;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MediaValueObjectTest extends TestCase
{
    public function testUuidV7IdValidatesInputAndGeneratesValue(): void
    {
        $generated = MediaId::generate();
        $restored = MediaId::fromString((string) $generated);

        self::assertTrue($generated->equals($restored));

        $this->expectException(InvalidDomainValueException::class);

        MediaId::fromString(Uuid::uuid4()->toString());
    }

    public function testStorageKeyUsesUuidV4(): void
    {
        $generated = MediaStorageKey::generate();
        $restored = MediaStorageKey::fromString((string) $generated);

        self::assertTrue($generated->equals($restored));

        $this->expectException(InvalidDomainValueException::class);

        MediaStorageKey::fromString(Uuid::uuid7()->toString());
    }

    public function testPathValidatesFormatAndShard(): void
    {
        $storageKey = MediaStorageKey::fromString('11111111-1111-4111-8111-111111111111');

        self::assertSame(
            'uploads/11/11111111-1111-4111-8111-111111111111/source.jpg',
            (string) MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
        );

        $this->expectException(InvalidDomainValueException::class);

        MediaPath::fromString('uploads/aa/11111111-1111-4111-8111-111111111111/source.jpg');
    }

    public function testMediaPathFactoriesBuildValidPaths(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame(
            'images/ab/ab111111-1111-4111-8111-111111111111/thumbnail.jpg',
            (string) MediaPath::imageConversion(
                storageKey: $storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'JPG',
            ),
        );
        self::assertSame(
            'images/ab/ab111111-1111-4111-8111-111111111111/source.png',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Image, extension: 'png'),
        );
        self::assertSame(
            'videos/ab/ab111111-1111-4111-8111-111111111111/source.mp4',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Video, extension: 'mp4'),
        );
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
        );
        self::assertSame(
            'videos/ab/ab111111-1111-4111-8111-111111111111/normalizedMp4H264.mp4',
            (string) MediaPath::videoConversion(
                storageKey: $storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'MP4',
            ),
        );
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/normalizedAacM4a.m4a',
            (string) MediaPath::audioConversion(
                storageKey: $storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
        );
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
            MediaPath::fromString('audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a')->value(),
        );
    }

    public function testMediaPathExtensionReadsLastSegment(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame('jpg', MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->extension());
        self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
    }

    public function testOriginalReadyRejectsUnsupportedMediaType(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPath::originalReady(
            storageKey: MediaStorageKey::generate(),
            type: MediaType::Document,
            extension: 'pdf',
        );
    }

    public function testWaveformValidatesPeaksAndSerializes(): void
    {
        $waveform = MediaWaveform::fromPeaks([0, 128, 255]);

        self::assertSame([0, 128, 255], $waveform->peaks());
        self::assertSame([0, 128, 255], $waveform->jsonSerialize());
        self::assertTrue($waveform->equals(MediaWaveform::fromPeaks([0, 128, 255])));
        self::assertFalse($waveform->equals(MediaWaveform::fromPeaks([0, 128, 254])));
    }

    public function testWaveformRejectsEmptyPeaks(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([]);
    }

    public function testWaveformRejectsPeakOutOfRange(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([0, 256]);
    }

    public function testMediaPathFactoryRejectsInvalidExtension(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPath::imageConversion(
            storageKey: MediaStorageKey::generate(),
            type: MediaImageConversionType::Thumbnail,
            extension: 'jp g',
        );
    }

    public function testMimeTypeValidatesLength(): void
    {
        self::assertSame('image/jpeg', (string) MediaMimeType::fromString('image/jpeg'));

        $this->expectException(InvalidDomainValueException::class);

        MediaMimeType::fromString('');
    }

    /**
     * @param class-string $valueObjectClass
     */
    #[DataProvider('integerValueObjectProvider')]
    public function testIntegerValueObjectsValidateBorders(string $valueObjectClass, int $valid, int $invalid): void
    {
        $validValue = $valueObjectClass::fromInt($valid);
        self::assertSame($valid, $validValue->value());
        self::assertSame($valid, $validValue->jsonSerialize());
        self::assertSame((string) $valid, (string) $validValue);
        self::assertTrue($valueObjectClass::supports($valid));
        self::assertFalse($valueObjectClass::supports($invalid));

        $this->expectException(InvalidDomainValueException::class);

        $valueObjectClass::fromInt($invalid);
    }

    public function testProcessingAttemptsCanIncrement(): void
    {
        self::assertSame(1, MediaProcessingAttempts::zero()->increment()->value());
    }

    public function testExpirationRepresentsPermanentAndTemporaryStates(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-05-21 18:41:00');

        self::assertTrue(MediaExpiration::permanent()->isPermanent());
        self::assertSame($expiresAt, MediaExpiration::temporaryUntil($expiresAt)->expiresAtOrFail());
        self::assertNull(MediaExpiration::permanent()->value());
    }

    public function testProcessingErrorRejectsUnsafeTechnicalData(): void
    {
        self::assertSame('Не удалось обработать изображение', (string) MediaProcessingError::fromString('Не удалось обработать изображение'));
        self::assertNull(MediaProcessingError::none()->value());

        $this->expectException(InvalidDomainValueException::class);

        MediaProcessingError::fromString('Ошибка uploads/11/11111111-1111-4111-8111-111111111111/source.jpg');
    }

    public function testMultipartValuesValidateInput(): void
    {
        self::assertSame('upload-id', (string) MediaMultipartUploadIdValue::fromString('upload-id'));
        self::assertSame('etag-value', (string) MediaMultipartPartETag::fromString('etag-value'));
    }

    public function testMultipartPartCollectionSortsPartsAndRejectsDuplicates(): void
    {
        $secondPart = MediaMultipartPart::create(
            partNumber: MediaMultipartPartNumber::fromInt(2),
            eTag: MediaMultipartPartETag::fromString('second'),
        );

exec
/bin/zsh -lc "sed -n '1,560p' tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class CompleteMediaUploadHandlerTest extends MediaApplicationTestCase
{
    public function testCompletesSingleUploadAndQueuesProcessing(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $this->persist($media);

        $captured = null;
        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
                $captured = $message;

                return StoredOutboxEventId::fromString('outbox-1');
            },
        );

        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->imagePlan($this->imageConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
        self::assertSame(MediaStatus::Uploaded, $media->status);
        self::assertInstanceOf(MediaUploaded::class, $captured);
        self::assertInstanceOf(MediaConversionPlan::class, $captured->plan);
        self::assertCount(1, $captured->plan->image);
    }

    public function testCompletesMultipartUpload(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $multipartUpload = $this->multipartUploadFor($media);
        $this->persist($media, $multipartUpload);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
        $fileService->expects(self::once())->method('completeMultipartUpload');

        $result = $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: $this->parts(),
        ));

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testCompletesVideoUploadWithVideoPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(
            userId: $userId,
            type: MediaType::Video,
            size: MediaFileSize::fromInt(2048),
            extension: 'mp4',
            mimeType: 'video/mp4',
        );
        $this->persist($media);

        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->videoPlan($this->videoConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testCompletesAudioUploadWithAudioPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(
            userId: $userId,
            type: MediaType::Audio,
            size: MediaFileSize::fromInt(2048),
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );
        $this->persist($media);

        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->audioPlan($this->audioConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testRejectsCrossTypePlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsDuplicateImageConversionTypes(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(), $this->imageConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsEmptyVideoPlanForVideoMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMultipleVideoProfiles(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(), $this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsVideoBitrateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(videoBitrate: 0)),
            parts: null,
        ));
    }

    public function testRejectsAudioSampleRateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(sampleRate: 1)),
            parts: null,
        ));
    }

    public function testRejectsAudioWaveformPeaksOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(waveformPeaks: 100_000)),
            parts: null,
        ));
    }

    public function testRejectsForeignListForVideoMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsVideoDimensionOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(width: 0)),
            parts: null,
        ));
    }

    public function testRejectsForeignListForAudioMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsEmptyAudioPlanForAudioMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMultipleAudioProfiles(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(), $this->audioConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsAudioBitrateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(bitrate: 0)),
            parts: null,
        ));
    }

    public function testRejectsDocumentMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(ForbiddenException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: UserId::generate()->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMediaNotWaitingUpload(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    #[DataProvider('outOfRangeConversionDimensionProvider')]
    public function testRejectsConversionDimensionsOutOfDomainRange(int $width, int $height): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(width: $width, height: $height)),
            parts: null,
        ));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function outOfRangeConversionDimensionProvider(): array
    {
        return [
            'нулевая ширина (нижняя граница)' => [0, 100],
            'нулевая высота (нижняя граница)' => [100, 0],
            'ширина выше максимума (верхняя граница)' => [200_000, 100],
            'высота выше максимума (верхняя граница)' => [100, 200_000],
        ];
    }

    public function testRejectsMultipartWithoutUploadRecord(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: $this->parts(),
        ));
    }

    public function testRejectsWhenUploadedObjectMissing(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(null);

        $this->expectException(ValidationException::class);

        $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsWhenUploadedObjectSizeMismatches(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(999), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        OutboxEventStoreContract $outboxStore,
    ): CompleteMediaUploadHandler {
        return new CompleteMediaUploadHandler(
            mediaRepository: $this->mediaRepository(),
            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
            mediaFileService: $fileService,
            outboxEventStore: $outboxStore,
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }

    private function fileServiceWithHead(int $contentLength): MediaFileServiceContract
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(
            new MediaObjectHead(contentLength: MediaFileSize::fromInt($contentLength)),
        );

        return $fileService;
    }

    private function outboxStore(): OutboxEventStoreContract
    {
        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
        $outboxStore->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));

        return $outboxStore;
    }

    private function multipartUploadFor(Media $media): MediaMultipartUpload
    {
        return MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-1'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: $media->size,
        );
    }

    private function parts(): MediaMultipartPartCollection
    {
        return new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('etag-1'),
            ),
        ]);
    }
}

exec
/bin/zsh -lc "sed -n '1,340p' tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,300p' tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Flow;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Symfony\Component\Process\Process;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Tests\Feature\Modules\Media\Application\MediaApplicationTestCase;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;

final class MediaProcessingFlowTest extends MediaApplicationTestCase
{
    use CleansOutboxEvents;

    /**
     * @var list<array{storage: MediaStorage, path: MediaPath}>
     */
    private array $createdObjects = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testCompleteThenRelayProcessesMediaToReady(): void
    {
        $bytes = $this->jpegBytes();
        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);

        $this->completeUpload($media, $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)));
        $publishedCount = $this->relay();

        self::assertSame(1, $publishedCount);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);
        self::assertSame(MediaStorage::Public, $processedMedia->storage);

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);

        $conversionPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Thumbnail,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
        $this->track(MediaStorage::Public, $conversionPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));

        // Идемпотентность повторной обработки: на ready — no-op, без дублей конверсий.
        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
        ));
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testProcessingRecoversFromProcessingFailedToReady(): void
    {
        $bytes = $this->jpegBytes();
        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);

        // Имитируем зафиксированный временный сбой: медиа осталось в processingFailed.
        $media->recordTemporaryProcessingError(
            MediaProcessingError::fromString('Временная ошибка обработки.'),
        );
        $this->persist($media);
        self::assertSame(MediaStatus::ProcessingFailed, $media->status);

        // Повторная доставка: ProcessMedia с валидным оригиналом доводит медиа до ready.
        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
        ));

        $readyMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($readyMedia);
        self::assertSame(MediaStatus::Ready, $readyMedia->status);
        self::assertSame(MediaStorage::Public, $readyMedia->storage);
        self::assertTrue($readyMedia->processingError->isEmpty());

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);

        $conversionPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Thumbnail,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
        $this->track(MediaStorage::Public, $conversionPath);
        $this->track(MediaStorage::Public, $readyPath);

        // Оригинал переложен в целевой бакет, конверсия залита.
        self::assertSame($readyPath->value(), $readyMedia->path->value());
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
    }

    public function testCompleteThenRelayProcessesVideoToReady(): void
    {
        $media = $this->uploadedOriginal(
            $this->videoBytes(),
            MediaVisibility::Public,
            MediaType::Video,
            'mp4',
            'video/mp4',
        );

        $this->completeUpload($media, $this->videoPlan($this->videoConversionSpec(width: 640, height: 480)));
        self::assertSame(1, $this->relay());

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($videoConversion);

        $normalizedPath = MediaPath::videoConversion(
            storageKey: $media->storageKey,
            type: MediaVideoConversionType::NormalizedMp4H264,
            extension: 'mp4',
        );
        $posterPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Poster,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4');
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $posterPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
    }

    public function testCompleteThenRelayProcessesAudioToReady(): void
    {
        $media = $this->uploadedOriginal(
            $this->audioBytes(),
            MediaVisibility::Public,
            MediaType::Audio,
            'wav',
            'audio/wav',
        );

        $this->completeUpload($media, $this->audioPlan($this->audioConversionSpec(waveformPeaks: 48)));
        self::assertSame(1, $this->relay());

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($audioConversion);
        self::assertCount(48, $audioConversion->waveform->peaks());

        $normalizedPath = MediaPath::audioConversion(
            storageKey: $media->storageKey,
            type: MediaAudioConversionType::NormalizedAacM4a,
            extension: 'm4a',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'wav');
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
    }

    public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
    {
        $corruptBytes = \random_bytes(2048);
        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);

        $outboxEventId = $this->completeUpload(
            $media,
            $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
        );
        $publishedCount = $this->relay();

        self::assertSame(0, $publishedCount);

        $failedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($failedMedia);
        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
        self::assertFalse($failedMedia->processingError->isEmpty());

        $outboxEvent = $this->getContainer()->get(OutboxEventRepository::class)->findById($outboxEventId);
        self::assertNotNull($outboxEvent);
        self::assertSame(OutboxEventStatus::Failed, $outboxEvent->status);
    }

    private function uploadedOriginal(
        string $bytes,
        MediaVisibility $visibility,
        MediaType $type = MediaType::Image,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: $visibility,
            type: $type,
            size: MediaFileSize::fromInt(\strlen($bytes)),
            extension: $extension,
            mimeType: $mimeType,
        );
        $this->persist($media);

        $this->fileService()->putObject(
            storage: MediaStorage::Upload,
            path: $media->path,
            contents: $bytes,
            mimeType: $media->mimeType,
        );
        $this->track(MediaStorage::Upload, $media->path);

        return $media;
    }

    private function completeUpload(Media $media, MediaConversionPlan $plan): OutboxEventId
    {
        $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new CompleteMediaUploadCommand(
                userId: $media->uploadedById->value(),
                mediaId: $media->id->value(),
                plan: $plan,
                parts: null,
            ),
            handler: $this->getContainer()->get(CompleteMediaUploadHandler::class)->handle(...),
        );

        $outboxEvent = $this->getContainer()->get(OutboxEventRepository::class)->findPendingForRelay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable(),
        )->first();

        self::assertNotNull($outboxEvent);

        return $outboxEvent->id;
    }

    private function relay(): int
    {
        return $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-01-01 00:00:00'),
        );
    }

    private function fileService(): MediaFileServiceContract
    {
        return $this->getContainer()->get(MediaFileServiceContract::class);
    }

    private function jpegBytes(): string
    {
        $image = \imagecreatetruecolor(200, 150);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 40, 160, 90));

        \ob_start();
        \imagejpeg($image);

        return (string) \ob_get_clean();
    }

    private function videoBytes(): string
    {
        return $this->ffmpegFixture('mp4', [
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-shortest', '-pix_fmt', 'yuv420p',
        ]);
    }

    private function audioBytes(): string
    {
        return $this->ffmpegFixture('wav', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=1']);
    }

    /**
     * @param list<string> $inputArgs
     */
    private function ffmpegFixture(string $extension, array $inputArgs): string
    {
        $ffmpegBinary = $this->getContainer()->get(MediaConfig::class)->ffmpegBinaryPath;
        $path = \sprintf('%s/flow_fixture_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(8)), $extension);

        $process = new Process([$ffmpegBinary, ...$inputArgs, '-y', $path]);
        $process->mustRun();

        $bytes = (string) \file_get_contents($path);
        \unlink($path);

        return $bytes;
    }

    private function track(MediaStorage $storage, MediaPath $path): void
    {
        $this->createdObjects[] = ['storage' => $storage, 'path' => $path];
    }

    #[\Override]
    protected function tearDown(): void

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadCommand;
use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\MediaPresignedPart;
use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
use App\Modules\Media\Application\Dto\MediaUploadMode;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Feature\Modules\Media\Flow\Fixture\RecordingMediaLogger;

final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
{
    public function testRequestsSingleUpload(): void
    {
        $capturedExpiresAt = null;
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('presignPut')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                MediaMimeType $mimeType,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): string {
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/put-url';
            },
        );

        $logger = new RecordingMediaLogger();
        $result = $this->handler($fileService, logger: $logger)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(presignedTtl: 300),
            fileMeta: $this->fileMeta(size: 1024),
        ));

        self::assertSame(MediaUploadMode::Single, $result->uploadMode);
        self::assertSame('http://minio/put-url', $result->putUrl);
        self::assertNull($result->parts);

        // TTL presigned-ссылки берётся из спеки (300), а не из конфиг-дефолта 900.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
        self::assertSame(300, $logger->contextFor('Запрошена загрузка медиа.')['presignedTtlSeconds']);

        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
        self::assertNotNull($media);
        self::assertSame(MediaStatus::WaitingUpload, $media->status);
    }

    public function testRequestsMultipartUploadWhenSizeReachesThreshold(): void
    {
        $capturedExpiresAt = null;
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('createMultipartUpload')->willReturn(MediaMultipartUploadIdValue::fromString('upload-1'));
        $fileService->method('presignUploadParts')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                MediaMultipartUploadIdValue $uploadId,
                MediaMultipartPartsCount $partsCount,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): MediaPresignedPartCollection {
                $capturedExpiresAt = $expiresAt;

                return new MediaPresignedPartCollection([new MediaPresignedPart(partNumber: 1, url: 'http://minio/part-1')]);
            },
        );
        $fileService->expects(self::never())->method('presignPut');

        $result = $this->handler($fileService, threshold: 5_242_880, partSize: 5_242_880)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(maxSize: 5_242_880, presignedTtl: 600),
            fileMeta: $this->fileMeta(size: 5_242_880),
        ));

        self::assertSame(MediaUploadMode::Multipart, $result->uploadMode);
        self::assertSame('upload-1', $result->uploadId);
        self::assertNotNull($result->parts);
        self::assertCount(1, $result->parts);
        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));

        // TTL частей multipart тоже берётся из спеки (600), а не из конфига.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+600 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testRejectsUnsupportedMimeType(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(mimeType: 'audio/mpeg'),
        ));
    }

    public function testRejectsMimeTypeOutsideSpec(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(mimeType: 'image/png'),
        ));
    }

    public function testRejectsSizeAboveSpecMax(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(maxSize: 512),
            fileMeta: $this->fileMeta(size: 1024),
        ));
    }

    public function testRejectsFileNameWithoutExtension(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(),
            fileMeta: $this->fileMeta(fileName: 'avatar'),
        ));
    }

    private function handler(
        MediaFileServiceContract|null $fileService = null,
        int $threshold = 16_777_216,
        int $partSize = 8_388_608,
        LoggerInterface|null $logger = null,
    ): RequestMediaUploadHandler {
        return new RequestMediaUploadHandler(
            mediaFileService: $fileService ?? $this->createStub(MediaFileServiceContract::class),
            mediaTypeResolver: new MediaTypeResolver(),
            mediaConfig: new MediaConfig(
                stagingTtlSeconds: 86_400,
                multipartThresholdBytes: $threshold,
                multipartPartSizeBytes: $partSize,
                imageProcessingDriver: 'imagick',
                ffmpegBinaryPath: '/usr/bin/ffmpeg',
                ffprobeBinaryPath: '/usr/bin/ffprobe',
                ffmpegTimeoutSeconds: 1800,
                ffmpegThreads: 0,
            ),
            entityManager: $this->entityManager(),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function spec(int $maxSize = 1_048_576, int $presignedTtl = 300): MediaUploadSpec
    {
        return new MediaUploadSpec(
            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
            maxSize: MediaFileSize::fromInt($maxSize),
            visibility: MediaVisibility::Private,
            presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
        );
    }

    private function fileMeta(
        string $fileName = 'avatar.jpg',
        string $mimeType = 'image/jpeg',
        int $size = 1024,
    ): MediaFileMeta {
        return new MediaFileMeta(
            fileName: $fileName,
            mimeType: MediaMimeType::fromString($mimeType),
            size: MediaFileSize::fromInt($size),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
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

    protected function imageConversionSpec(
        MediaImageConversionType $type = MediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaImageConversionSpec {
        return new MediaImageConversionSpec(type: $type, width: $width, height: $height);
    }

    protected function videoConversionSpec(
        MediaVideoConversionType $type = MediaVideoConversionType::NormalizedMp4H264,
        int $width = 1280,
        int $height = 720,
        int $videoBitrate = 1_000_000,
        int $audioBitrate = 128_000,
    ): MediaVideoConversionSpec {
        return new MediaVideoConversionSpec(
            type: $type,
            width: $width,
            height: $height,
            videoBitrate: $videoBitrate,
            audioBitrate: $audioBitrate,
        );
    }

    protected function audioConversionSpec(
        MediaAudioConversionType $type = MediaAudioConversionType::NormalizedAacM4a,
        int $bitrate = 128_000,
        int $sampleRate = 44_100,
        int $waveformPeaks = 64,
    ): MediaAudioConversionSpec {
        return new MediaAudioConversionSpec(
            type: $type,
            bitrate: $bitrate,
            sampleRate: $sampleRate,
            waveformPeaks: $waveformPeaks,
        );
    }

    protected function emptyPlan(): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: []);
    }

    protected function imagePlan(MediaImageConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: \array_values($specs), video: [], audio: []);
    }

    protected function videoPlan(MediaVideoConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: \array_values($specs), audio: []);
    }

    protected function audioPlan(MediaAudioConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: \array_values($specs));
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

    protected function audioConversionRepository(): MediaAudioConversionRepository
    {
        return $this->getContainer()->get(MediaAudioConversionRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php && sed -n '1,80p' app/src/Modules/Media/Application/Dto/MediaUploadSpec.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaMimeType>
 */
final class MediaMimeTypeCollection extends Collection
{
    public function containsMimeType(MediaMimeType $mimeType): bool
    {
        return $this->contains(static fn(MediaMimeType $allowed): bool => $allowed->equals($mimeType));
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;

/**
 * Спецификация ограничений загрузки, которую потребитель передаёт в RequestMediaUpload.
 * In-process DTO: держит политику загрузки на стороне серверного модуля-потребителя.
 */
final readonly class MediaUploadSpec
{
    public function __construct(
        public MediaMimeTypeCollection $allowedMimeTypes,
        public MediaFileSize $maxSize,
        public MediaVisibility $visibility,
        public MediaPresignedTtl $presignedTtl,
    ) {}
}

codex
Есть один существенный фактический риск: резолвер будет нормализовать MIME, но проверка `allowedMimeTypes` сейчас сравнивает исходную строку целиком. Дочитаю смежные DTO и запрос URL, чтобы не упустить похожие расхождения.
exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Media/Application/Dto/MediaConversionPlan.php && sed -n '1,120p' app/src/Modules/Media/Application/Dto/MediaFileMeta.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * План конверсий на тип медиа: отдельный список профилей для каждого MediaType. Потребитель
 * задаёт списки на тип, валидируется только список, относящийся к типу медиа.
 *
 * Все три списка несут точные PHPDoc-типы list<...Spec> — это условие восстановления вложенного
 * DTO через ValinorOutboxMessageSerializer (план кладётся в outbox-сообщение MediaUploaded).
 */
final readonly class MediaConversionPlan
{
    /**
     * @param list<MediaImageConversionSpec> $image
     * @param list<MediaVideoConversionSpec> $video
     * @param list<MediaAudioConversionSpec> $audio
     */
    public function __construct(
        public array $image,
        public array $video,
        public array $audio,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;

/**
 * Метаданные загружаемого файла. fileName используется только для извлечения расширения
 * и не хранится; mimeType и size — доменные VO.
 */
final readonly class MediaFileMeta
{
    public function __construct(
        public string $fileName,
        public MediaMimeType $mimeType,
        public MediaFileSize $size,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php && sed -n '1,120p' app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetMediaUrl;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
        private MediaFileServiceContract $mediaFileService,
    ) {}

    public function handle(GetMediaUrlQuery $query): MediaUrlResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->isReady()) {
            throw new NotFoundException('app.media.not_ready');
        }

        if ($query->conversionType !== null) {
            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
                ?? throw new NotFoundException('app.media.conversion_not_found');

            return $this->buildUrl(
                visibility: $media->visibility,
                storage: $conversion->storage,
                path: $conversion->path,
                presignedTtlSeconds: $query->presignedTtlSeconds,
            );
        }

        return $this->buildUrl(
            visibility: $media->visibility,
            storage: $media->storage,
            path: $media->path,
            presignedTtlSeconds: $query->presignedTtlSeconds,
        );
    }

    private function findConversion(
        MediaId $mediaId,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaImageConversion|MediaVideoConversion|MediaAudioConversion|null {
        // У изображений несколько типов — выбираем нужный по type. У видео и аудио каталог типов
        // содержит ровно один профиль и на медиа не больше одной такой конверсии, поэтому берём
        // первую (фильтр по единственному типу был бы всегда истинным).
        return match (true) {
            $type instanceof MediaImageConversionType => $this->mediaImageConversionRepository->findByMediaId($mediaId)->first(
                static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
            ),
            $type instanceof MediaVideoConversionType => $this->mediaVideoConversionRepository->findByMediaId($mediaId)->first(),
            $type instanceof MediaAudioConversionType => $this->mediaAudioConversionRepository->findByMediaId($mediaId)->first(),
        };
    }

    private function buildUrl(
        MediaVisibility $visibility,
        MediaStorage $storage,
        MediaPath $path,
        int $presignedTtlSeconds,
    ): MediaUrlResult {
        if ($visibility === MediaVisibility::Public) {
            return new MediaUrlResult(
                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
                expiresAt: null,
            );
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
        );

        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
            expiresAt: $expiresAt,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetMediaUrl;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

final readonly class GetMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int $presignedTtlSeconds,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null $conversionType = null,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,340p' tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsDirectPublicUrlForPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
        ));

        // Для public TTL не применяется и VO не строится: прямой URL без срока.
        self::assertSame('http://minio/media-public/object', $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testIgnoresInvalidTtlForPublicMedia(): void
    {
        // Для public-медиа срок не применяется и VO не строится, поэтому заведомо
        // невалидный TTL (вне диапазона MediaPresignedTtl) проходит как успех без presignGet.
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 0,
        ));

        self::assertSame('http://minio/media-public/object', $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testReturnsPresignedUrlForPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);

        $capturedExpiresAt = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedExpiresAt): string {
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/signed';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
        ));

        self::assertSame('http://minio/signed', $result->url);
        self::assertNotNull($result->expiresAt);

        // TTL presigned GET берётся из запроса (300), а не из конфиг-дефолта 900.
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testReturnsUrlForRequestedConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $conversion = $this->thumbnailConversion($media);
        $this->persist($media, $conversion);

        $capturedPath = null;
        $capturedExpiresAt = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static function (
                MediaStorage $storage,
                MediaPath $path,
                \DateTimeImmutable $expiresAt,
            ) use (&$capturedPath, &$capturedExpiresAt): string {
                $capturedPath = $path->value();
                $capturedExpiresAt = $expiresAt;

                return 'http://minio/signed-thumbnail';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 450,
            conversionType: MediaImageConversionType::Thumbnail,
        ));

        self::assertSame('http://minio/signed-thumbnail', $result->url);
        self::assertSame($conversion->path->value(), $capturedPath);

        // Конверсия private отдаёт presigned URL с тем же сроком из запроса (450).
        self::assertNotNull($capturedExpiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+450 seconds')->getTimestamp(),
            $capturedExpiresAt->getTimestamp(),
            5,
        );
    }

    public function testReturnsUrlForVideoConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $conversion = $this->videoConversion($media);
        $this->persist($media, $conversion);

        $capturedPath = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static function (MediaStorage $storage, MediaPath $path) use (&$capturedPath): string {
                $capturedPath = $path->value();

                return 'http://minio/media-public/normalized.mp4';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
            conversionType: MediaVideoConversionType::NormalizedMp4H264,
        ));

        self::assertSame('http://minio/media-public/normalized.mp4', $result->url);
        self::assertSame($conversion->path->value(), $capturedPath);
        self::assertNull($result->expiresAt);
    }

    public function testReturnsUrlForAudioConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $conversion = $this->audioConversion($media);
        $this->persist($media, $conversion);

        $capturedPath = null;
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static function (MediaStorage $storage, MediaPath $path) use (&$capturedPath): string {
                $capturedPath = $path->value();

                return 'http://minio/media-public/normalized.m4a';
            },
        );

        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
            conversionType: MediaAudioConversionType::NormalizedAacM4a,
        ));

        self::assertSame('http://minio/media-public/normalized.m4a', $result->url);
        self::assertSame($conversion->path->value(), $capturedPath);
    }

    public function testRejectsMissingConversion(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);

        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
            mediaId: $media->id->value(),
            presignedTtlSeconds: 300,
            conversionType: MediaImageConversionType::Poster,
        ));
    }

    public function testRejectsMediaThatIsNotReady(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))
            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))
            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
    }

    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
    {
        return new GetMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaImageConversionRepository: $this->imageConversionRepository(),
            mediaVideoConversionRepository: $this->videoConversionRepository(),
            mediaAudioConversionRepository: $this->audioConversionRepository(),
            mediaFileService: $fileService,
        );
    }

    private function readyMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }

    private function videoConversion(Media $media): MediaVideoConversion
    {
        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::videoConversion(
                storageKey: $media->storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'mp4',
            ),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
        );
    }

    private function audioConversion(Media $media): MediaAudioConversion
    {
        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::audioConversion(
                storageKey: $media->storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        );
    }

    private function thumbnailConversion(Media $media): MediaImageConversion
    {
        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,340p' app/src/Modules/Media/Domain/Entity/Media.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
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

    #[HasMany(
        target: MediaAudioConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaAudioConversionCollection::class,
    )]
    public private(set) MediaAudioConversionCollection $audioConversions;

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
        $media->audioConversions = new MediaAudioConversionCollection();
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
     * processing или processingFailed (повтор обработки после временной ошибки).
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

codex
`GetMediaUrl` действительно не зависит от типа, так что отдельной правки там не нужно. Проверю ещё места, где другие модули собирают `MediaUploadSpec`: если план обещает только модуль Media, надо явно отделить это от реальной доступности документов в функциях приложения.
exec
/bin/zsh -lc 'rg -n "copyObject|publicUrl|presignGet|bucket|prefix|path->value|MediaStorage" app/src/Modules/Media/Infrastructure app/config tests/Feature/Modules/Media/Infrastructure' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/config/cache.php:26:        //     'prefix' => 'user_'
app/config/session.php:22:            'prefix' => \env('SESSION_CACHE_PREFIX', 'session:'),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:12:use App\Modules\Media\Domain\Enum\MediaStorage;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:20:use App\Modules\Media\Infrastructure\Exception\MediaStorageNotConfiguredException;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:50:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:57:            'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:67:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:73:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:90:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:97:        $bucket = $this->bucketName($storage);
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:103:                'Bucket' => $bucket,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:120:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:136:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:154:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:160:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:174:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:178:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:197:    public function getObjectContents(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:201:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:216:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:224:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:243:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:250:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:262:        MediaStorage $storage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:269:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:280:    public function copyObject(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:281:        MediaStorage $fromStorage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:283:        MediaStorage $toStorage,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:286:        $copySource = \sprintf('%s/%s', $this->bucketName($fromStorage), $this->objectKey(storage: $fromStorage, path: $fromPath));
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:289:            $this->client($toStorage)->copyObject([
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:290:                'Bucket' => $this->bucketName($toStorage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:300:    public function deleteObject(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:304:                'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:317:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:321:            'Bucket' => $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:329:    public function publicUrl(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:332:            bucket: $this->bucketName($storage),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:337:    private function bucketConfig(MediaStorage $storage): StorageBucketConfig
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:339:        return $this->storageConfig->buckets[$storage->value]
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:340:            ?? throw MediaStorageNotConfiguredException::bucketAliasMissing($storage);
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:343:    private function bucketName(MediaStorage $storage): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:345:        return $this->bucketConfig($storage)->bucket
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:346:            ?? throw MediaStorageNotConfiguredException::bucketNameMissing($storage);
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:349:    private function objectKey(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:351:        $prefix = $this->bucketConfig($storage)->prefix;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:353:        if ($prefix === null || $prefix === '') {
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:354:            return $path->value();
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:357:        return \sprintf('%s/%s', \rtrim(string: $prefix, characters: '/'), $path->value());
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:360:    private function client(MediaStorage $storage): S3Client
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:362:        $bucketConfig = $this->bucketConfig($storage);
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:363:        $serverConfig = $this->storageConfig->servers[$bucketConfig->server]
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:364:            ?? throw MediaStorageNotConfiguredException::serverMissing(storage: $storage, server: $bucketConfig->server);
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:7:use App\Modules\Media\Domain\Enum\MediaStorage;
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:9:final class MediaStorageNotConfiguredException extends \DomainException
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:11:    public static function bucketAliasMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:16:    public static function bucketNameMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:18:        return new self(\sprintf('Для bucket-алиаса %s не задано имя бакета.', $storage->value));
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:21:    public static function serverMissing(MediaStorage $storage, string $server): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:23:        return new self(\sprintf('Сервер %s для bucket-алиаса %s не настроен.', $server, $storage->value));
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:11:use App\Modules\Media\Domain\Enum\MediaStorage;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:39:        MediaStorage $sourceStorage,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:42:        MediaStorage $targetStorage,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:8:use App\Modules\Media\Domain\Enum\MediaStorage;
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:16:use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:28:     * @var list<array{storage: MediaStorage, path: MediaPath}>
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:37:        $this->track(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:40:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:50:        $head = $fileService->headObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:62:        $this->track(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:65:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:70:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:83:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:94:        $head = $fileService->headObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:105:        $this->track(MediaStorage::Upload, $uploadPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:106:        $this->track(MediaStorage::Public, $publicPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:109:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:114:        $fileService->copyObject(
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:115:            fromStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:117:            toStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:121:        $publicUrl = $fileService->publicUrl(MediaStorage::Public, $publicPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:122:        $publicResponse = $this->httpClient()->get($publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:124:        self::assertStringContainsString('media-public', $publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:125:        self::assertStringContainsString('test/', $publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:135:        $this->track(MediaStorage::Private, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:138:            storage: MediaStorage::Private,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:144:        $presignedUrl = $fileService->presignGet(MediaStorage::Private, $path, $this->expiresAt());
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:158:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:163:        self::assertNotNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:165:        $fileService->deleteObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:166:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:169:        $fileService->deleteObject(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:170:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:178:        $this->track(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:181:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:187:        self::assertSame($bytes, $fileService->getObjectContents(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:192:        self::assertNull($this->fileService()->headObject(MediaStorage::Upload, $this->uploadPath()));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:201:        $this->track(MediaStorage::Upload, $sourcePath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:202:        $this->track(MediaStorage::Public, $targetPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:205:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:211:        $localFile = $fileService->downloadToFile(MediaStorage::Upload, $sourcePath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:217:            storage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:224:        self::assertSame($bytes, $fileService->getObjectContents(MediaStorage::Public, $targetPath));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:233:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:238:        $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:240:        $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:242:        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:251:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:262:                storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:273:            $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:304:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:311:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:321:    private function track(MediaStorage $storage, MediaPath $path): void
app/config/storage.php:53:            'bucket' => \env('S3_BUCKET', 'yoga-loka'),
app/config/storage.php:59:            'prefix' => '',
app/config/storage.php:72:    'buckets' => [
app/config/storage.php:78:            'bucket' => \env('S3_BUCKET', 'yoga-loka'),
app/config/storage.php:82:            'bucket' => \env('S3_TEST_BUCKET', 'yoga-loka-test'),
app/config/storage.php:86:            'bucket' => \env('MEDIA_UPLOAD_STORAGE_BUCKET', 'media-upload'),
app/config/storage.php:87:            'prefix' => \env('MEDIA_UPLOAD_STORAGE_PREFIX', null),
app/config/storage.php:92:            'bucket' => \env('MEDIA_PRIVATE_STORAGE_BUCKET', 'media-private'),
app/config/storage.php:93:            'prefix' => \env('MEDIA_PRIVATE_STORAGE_PREFIX', null),
app/config/storage.php:98:            'bucket' => \env('MEDIA_PUBLIC_STORAGE_BUCKET', 'media-public'),
app/config/storage.php:99:            'prefix' => \env('MEDIA_PUBLIC_STORAGE_PREFIX', null),
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:14:use App\Modules\Media\Domain\Enum\MediaStorage;
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:18:use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:32:     * @var list<array{storage: MediaStorage, path: MediaPath}>
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:38:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:54:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:57:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:61:        $this->track(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:62:        $this->track(MediaStorage::Public, $posterPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:83:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:84:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:87:        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:94:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:112:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:115:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:119:        $this->track(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:120:        $this->track(MediaStorage::Public, $posterPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:123:        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:130:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:144:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:147:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:150:        $this->track(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:156:        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:163:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:174:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:177:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:180:        $this->track(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:192:        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:197:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:210:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:213:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:216:        $this->track(MediaStorage::Public, $normalizedPath);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:223:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:230:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:233:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:249:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:256:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:259:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:270:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:280:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:283:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:294:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:300:                sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:303:                targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:323:        $storageKey = MediaStorageKey::generate();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:328:            sourceStorage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:331:            targetStorage: MediaStorage::Public,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:381:            storage: MediaStorage::Upload,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:386:        $this->track(MediaStorage::Upload, $path);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:444:    private function probeStreamCodecs(MediaStorage $storage, MediaPath $path): array
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:493:    private function track(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:11:use App\Modules\Media\Domain\Enum\MediaStorage;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:43:        MediaStorage $sourceStorage,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:46:        MediaStorage $targetStorage,

exec
/bin/zsh -lc 'rg -n "new MediaUploadSpec|allowedMimeTypes|MediaMimeTypeCollection|RequestMediaUpload" app/src tests | head -200' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc 'rg -n "document|application/pdf|text/csv|text/plain|image/vnd.djvu|MediaType::Document|documents" app/src app/locale app/config docs/rules.md docs/arch.md tests | head -240' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Domain/Enum/MediaType.php:12:    case Document = 'document';
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:83:            MediaType::Document => throw new InvalidDomainValueException(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123:            MediaType::Document => throw new ValidationException(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:348:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:90:            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:257:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:259:            mimeType: 'application/pdf',
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:21:        self::assertSame(['image', 'video', 'audio', 'document'], $this->values(MediaType::cases()));
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:138:            type: MediaType::Document,
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:39:        return [['application/pdf'], ['text/plain'], ['font/woff2']];

 succeeded in 0ms:
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:7:use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadCommand;
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:8:use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadHandler;
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:16:use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:34:final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:54:        $result = $this->handler($fileService, logger: $logger)->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:98:        $result = $this->handler($fileService, threshold: 5_242_880, partSize: 5_242_880)->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:123:        $this->handler()->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:134:        $this->handler()->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:145:        $this->handler()->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:156:        $this->handler()->handle(new RequestMediaUploadCommand(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:168:    ): RequestMediaUploadHandler {
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:169:        return new RequestMediaUploadHandler(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:189:        return new MediaUploadSpec(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:190:            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:13:final class MediaMimeTypeCollection extends Collection
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:5:namespace App\Modules\Media\Application\Command\RequestMediaUpload;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:10:final readonly class RequestMediaUploadCommand
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:5:namespace App\Modules\Media\Application\Command\RequestMediaUpload;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:9:use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:27:final readonly class RequestMediaUploadHandler
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:38:    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:74:    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:84:        return RequestMediaUploadResult::single(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:91:    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:117:        return RequestMediaUploadResult::multipart(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:127:        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:7:use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:13: * Спецификация ограничений загрузки, которую потребитель передаёт в RequestMediaUpload.
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:8: * Результат RequestMediaUpload. Для single заполнен putUrl; для multipart — uploadId и parts.
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:11:final readonly class RequestMediaUploadResult
app/src/Modules/Media/README.md:19:| `RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)` | Command | `RequestMediaUploadResult` |
app/src/Modules/Media/README.md:38:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/README.md:51:- `RequestMediaUploadResult` — `single`: `putUrl`; `multipart`: `uploadId` + коллекция
app/src/Modules/Media/README.md:59:1. RequestMediaUpload  -> presigned PUT (single) или multipart-ссылки; media = waitingUpload
app/src/Modules/Media/README.md:154:  `phpunit.xml`. `MediaConfig` инжектится напрямую в `RequestMediaUploadHandler` — осознанное

codex
Смежные сервисы хранения работают по `MediaPath`, а не по типу, это подтверждает реализуемость `documents/...`. Последняя проверка — сценарии, которые проверяют тип медиа, чтобы понять, не нужно ли плану явно защитить документ от старых image-only проверок.
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php && sed -n '1,180p' app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Не бросающий вариант GetMediaUrl для best-effort отображения (например, аватара в профиле):
 * если медиа отсутствует или ещё не готово — возвращает null, чтобы вызывающий подставил значение
 * по умолчанию без try-catch. Для публичного медиа отдаёт прямой URL, для приватного — presigned.
 */
final readonly class FindMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));

        if ($media === null || !$media->isReady()) {
            return null;
        }

        if ($media->visibility === MediaVisibility::Public) {
            return new MediaUrlResult(
                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
                expiresAt: null,
            );
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($query->presignedTtlSeconds)->value())),
        );

        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
            expiresAt: $expiresAt,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\DeleteMedia;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class DeleteMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
        private MediaFileServiceContract $mediaFileService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(DeleteMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if ($media->status === MediaStatus::WaitingUpload) {
            $this->abortActiveMultipartUpload($media);
        }

        // Объекты конверсий лежат в целевом бакете и каскадно удаляются из БД, но не из S3 —
        // удаляем их явно до delete(), иначе остаются осиротевшие файлы. 404 игнорируется сервисом.
        $this->deleteConversionObjects($media);

        // Удаляем текущий оригинал (staging или целевой бакет). 404 игнорируется сервисом.
        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);

        $this->entityManager->delete($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа удалено.', context: [
            'mediaId' => $command->mediaId,
            'userId' => $command->userId,
        ]);
    }

    private function deleteConversionObjects(Media $media): void
    {
        foreach ($this->mediaImageConversionRepository->findByMediaId($media->id) as $imageConversion) {
            $this->mediaFileService->deleteObject(storage: $imageConversion->storage, path: $imageConversion->path);
        }

        foreach ($this->mediaVideoConversionRepository->findByMediaId($media->id) as $videoConversion) {
            $this->mediaFileService->deleteObject(storage: $videoConversion->storage, path: $videoConversion->path);
        }

        // Постер видео — это MediaImageConversion (Poster), он уже попадёт в image-цикл выше;
        // здесь чистим только аудио-конверсии, чтобы не задваивать постер.
        foreach ($this->mediaAudioConversionRepository->findByMediaId($media->id) as $audioConversion) {
            $this->mediaFileService->deleteObject(storage: $audioConversion->storage, path: $audioConversion->path);
        }
    }

    private function abortActiveMultipartUpload(Media $media): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id);

        if ($multipartUpload === null) {
            return;
        }

        $this->mediaFileService->abortMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
        );
    }
}

exec
/bin/zsh -lc 'rg -n "MediaType::|isImage|CheckMedia|media->type|type ===|type !==|conversionType" app/src/Modules tests/Feature/Modules tests/Unit/Modules | head -300' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:21:        self::assertSame(['image', 'video', 'audio', 'document'], $this->values(MediaType::cases()));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:76:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:123:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:138:            MediaType::Video,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:163:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:178:            MediaType::Audio,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:199:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'wav');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:232:        MediaType $type = MediaType::Image,
app/src/Modules/Media/README.md:25:| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (`conversionType` — union image/video/audio-enum или null; для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — прямой URL без срока; срок применяется только для private) |
app/src/Modules/Media/README.md:27:| `CheckMediaIsImage(mediaId)` | Query | `bool` |
app/src/Modules/Media/README.md:28:| `CheckMediaExists(mediaId)` | Query | `bool` |
app/src/Modules/Media/Domain/Entity/Media.php:50:    #[Column(type: 'string(32)', typecast: MediaType::class)]
app/src/Modules/Media/Domain/Entity/Media.php:123:        $media->type = $type;
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:36:        self::assertSame(MediaType::Image, $media->type);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:201:            type: MediaType::Image,
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:212:            type: MediaType::Image,
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:92:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Image, extension: 'png'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:96:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Video, extension: 'mp4'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:100:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:138:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:94:            type: MediaType::Video,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:118:            type: MediaType::Audio,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:172:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:188:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:204:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:220:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:236:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:252:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:268:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:284:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:300:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:316:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:332:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:348:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:40:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:54:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:93:            type: MediaType::Audio,
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:100:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:80:            MediaType::Image => 'images',
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:81:            MediaType::Video => 'videos',
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:82:            MediaType::Audio => 'audios',
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:83:            MediaType::Document => throw new InvalidDomainValueException(
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:68:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:124:            type: MediaType::Image,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:134:            conversionType: MediaImageConversionType::Thumbnail,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:168:            conversionType: MediaVideoConversionType::NormalizedMp4H264,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:195:            conversionType: MediaAudioConversionType::NormalizedAacM4a,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:212:            conversionType: MediaImageConversionType::Poster,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:253:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:90:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:237:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:257:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:335:            type: MediaType::Video,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:349:            type: MediaType::Audio,
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:36:        MediaType $type = MediaType::Image,
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:246:            type: MediaType::Image,
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:44:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:215:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:240:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:244:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:269:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:273:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:7:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:8:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:19:final class CheckMediaAttachableHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:27:        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:39:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:52:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:66:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:72:    private function handler(): CheckMediaAttachableHandler
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:74:        return new CheckMediaAttachableHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:83:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:7:use App\Modules\Media\Application\Query\CheckMediaExists\CheckMediaExistsHandler;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:8:use App\Modules\Media\Application\Query\CheckMediaExists\CheckMediaExistsQuery;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:9:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageHandler;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:10:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageQuery;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:14:final class CheckMediaQueriesTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:16:    public function testCheckMediaIsImageReturnsTrueForImage(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:18:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Image);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:21:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:23:        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:26:    public function testCheckMediaIsImageReturnsFalseForVideo(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:28:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:31:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:33:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:36:    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:38:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:40:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:43:    public function testCheckMediaExistsReturnsTrueForExistingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:48:        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:50:        self::assertTrue($handler->handle(new CheckMediaExistsQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:53:    public function testCheckMediaExistsReturnsFalseForMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:55:        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:57:        self::assertFalse($handler->handle(new CheckMediaExistsQuery(mediaId: UserId::generate()->value())));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:264:            type: MediaType::Image,
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:20:        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:21:        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:22:        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mpeg')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:23:        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:11:final readonly class CheckMediaIsImageHandler
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:21:        return $media !== null && $media->type === MediaType::Image;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:7:final readonly class CheckMediaIsImageQuery
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:34:        if ($media->type !== MediaType::Audio) {
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:16:        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null $conversionType = null,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:45:        if ($query->conversionType !== null) {
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:46:            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:74:                static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaExists;
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:10:final readonly class CheckMediaExistsHandler
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:16:    public function handle(CheckMediaExistsQuery $query): bool
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaExists;
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:7:final readonly class CheckMediaExistsQuery
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:7:final readonly class CheckMediaAttachableQuery
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:20:final readonly class CheckMediaAttachableHandler
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:27:    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:23:            return MediaType::Image;
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:27:            return MediaType::Video;
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:31:            return MediaType::Audio;
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:60:        $this->assertPlanValid(plan: $command->plan, type: $media->type);
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:120:            MediaType::Image => $this->assertImagePlan($plan),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:121:            MediaType::Video => $this->assertVideoPlan($plan),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:122:            MediaType::Audio => $this->assertAudioPlan($plan),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123:            MediaType::Document => throw new ValidationException(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:67:            'type' => $media->type->value,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:73:        $conversions = match ($media->type) {
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:74:            MediaType::Image => $this->buildImageConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:80:            MediaType::Video => $this->buildVideoConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:85:            MediaType::Audio => $this->buildAudioConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:90:            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:110:        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:31:        if ($type !== null && $type !== OutboxQueueEnvelope::class) {
app/src/Modules/Auth/Domain/Entity/AuthToken.php:91:        return $this->type === AuthTokenType::Access;
app/src/Modules/Auth/Domain/Entity/AuthToken.php:96:        return $this->type === AuthTokenType::Refresh;
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:94:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:150:            static fn(object $message): bool => $message instanceof NotificationRequested && $message->type === $type,
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:283:            type: MediaType::Image,
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:74:            type: MediaType::Image,
app/src/Modules/Posts/Application/Post/PostContentComposer.php:9:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
app/src/Modules/Posts/Application/Post/PostContentComposer.php:10:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
app/src/Modules/Posts/Application/Post/PostContentComposer.php:43:        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
app/src/Modules/Posts/Application/Post/PostContentComposer.php:81:                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:80:        if ($userId === null || $type === null || $sessionId === null) {
tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php:108:            type: MediaType::Image,

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php 2>/dev/null || true && rg -n \"CheckMediaAttachable|CheckMediaIsImage|Attachable\" app/src/Modules/Media app/src/Modules/User app/src/Modules/Posts tests -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверяет, можно ли вложить медиа в запись: оно должно существовать (иначе 404), принадлежать
 * владельцу (иначе 403) и быть обработанным/готовым (иначе 422). Вызывается до MakeMediaPermanent,
 * поэтому тот всегда получает готовое медиа.
 */
final readonly class CheckMediaAttachableHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($query->ownerUserId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if (!$media->isReady()) {
            throw new ValidationException('app.media.not_ready');
        }

        return new MediaAttachableResult(mediaId: $media->id->value());
    }
}
app/src/Modules/Media/README.md:27:| `CheckMediaIsImage(mediaId)` | Query | `bool` |
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:11:final readonly class CheckMediaIsImageHandler
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:7:final readonly class CheckMediaIsImageQuery
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:7:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:8:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:19:final class CheckMediaAttachableHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:27:        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:39:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:52:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:66:        $this->handler()->handle(new CheckMediaAttachableQuery(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:72:    private function handler(): CheckMediaAttachableHandler
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:74:        return new CheckMediaAttachableHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:9:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageHandler;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:10:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageQuery;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:16:    public function testCheckMediaIsImageReturnsTrueForImage(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:21:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:23:        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:26:    public function testCheckMediaIsImageReturnsFalseForVideo(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:31:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:33:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:36:    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:38:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:40:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:7:final readonly class CheckMediaAttachableQuery
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:10:final readonly class MediaAttachableResult
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:20:final readonly class CheckMediaAttachableHandler
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:27:    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:40:        return new MediaAttachableResult(mediaId: $media->id->value());
app/src/Modules/Posts/Application/Post/PostContentComposer.php:9:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
app/src/Modules/Posts/Application/Post/PostContentComposer.php:10:use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
app/src/Modules/Posts/Application/Post/PostContentComposer.php:43:        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
app/src/Modules/Posts/Application/Post/PostContentComposer.php:81:                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
app/src/Modules/Posts/Application/Post/PostContentComposer.php:82:                handler: $this->checkMediaAttachableHandler->handle(...),

codex
Ещё один тестовый риск: многие текущие негативные тесты ловят только класс исключения. Для нового сценария это может скрыть неправильную причину отказа, поэтому в ревью отмечу, где нужен контроль именно ключа ошибки.
exec
/bin/zsh -lc "sed -n '1,160p' app/src/Shared/Domain/Exception/ValidationException.php && sed -n '1,160p' app/src/Shared/Domain/Exception/DomainException.php 2>/dev/null || true" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class ValidationException extends DomainTranslatableException
{
    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}

exec
/bin/zsh -lc "rg -n \"class ValidationException|translationKey|function .*translation|public .*translation|extends\" app/src/Shared app/src -g '*Exception.php' | head -120" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Outbox/Application/Exception/OutboxMessageLoadingException.php:10:final class OutboxMessageLoadingException extends \DomainException
app/src/Modules/Outbox/Application/Exception/OutboxMessageSerializationException.php:7:final class OutboxMessageSerializationException extends \DomainException
app/src/Shared/Domain/Exception/InvalidDomainValueException.php:7:final class InvalidDomainValueException extends \DomainException
app/src/Shared/Domain/Exception/DomainTranslatableException.php:9:abstract class DomainTranslatableException extends \DomainException implements TranslatableException
app/src/Shared/Domain/Exception/DomainTranslatableException.php:14:    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
app/src/Shared/Domain/Exception/DomainTranslatableException.php:16:        parent::__construct(message: $translationKey, code: $this->statusCode());
app/src/Shared/Domain/Exception/DomainTranslatableException.php:22:    public function translationKey(): string
app/src/Shared/Domain/Exception/DomainTranslatableException.php:24:        return $this->translationKey;
app/src/Shared/Domain/Exception/DomainTranslatableException.php:33:    public function translationDomain(): string
app/src/Shared/Domain/Exception/DomainTranslatableException.php:35:        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
app/src/Shared/Domain/Exception/DomainTranslatableException.php:42:    public function translationParameters(): array
app/src/Shared/Domain/Exception/AuthenticationException.php:7:final class AuthenticationException extends DomainTranslatableException
app/src/Shared/Domain/Exception/ValidationException.php:7:final class ValidationException extends DomainTranslatableException
app/src/Shared/Domain/Exception/NotFoundException.php:7:final class NotFoundException extends DomainTranslatableException
app/src/Shared/Domain/Exception/ForbiddenException.php:7:final class ForbiddenException extends DomainTranslatableException
app/src/Modules/Notifications/Application/Exception/NotificationTypeRegistryException.php:14:final class NotificationTypeRegistryException extends \DomainException
app/src/Modules/Notifications/Application/Exception/FcmPushFailedException.php:15:final class FcmPushFailedException extends \DomainException
app/src/Modules/Notifications/Application/Exception/CentrifugoPublishException.php:14:final class CentrifugoPublishException extends \DomainException
app/src/Modules/System/Presentation/Exception/OpenApiAssetsPublicationException.php:7:final class OpenApiAssetsPublicationException extends \Exception {}
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php:7:final class MediaImageProcessorException extends \DomainException
app/src/Shared/Infrastructure/Exception/InvalidConfigValueException.php:7:final class InvalidConfigValueException extends \InvalidArgumentException
app/src/Shared/Infrastructure/Exception/ConfigMappingException.php:9:final class ConfigMappingException extends \RuntimeException
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:9:final class MediaStorageNotConfiguredException extends \DomainException
app/src/Modules/Outbox/Infrastructure/Exception/OutboxRelayStoppedException.php:7:final class OutboxRelayStoppedException extends \DomainException
app/src/Modules/Outbox/Infrastructure/Exception/OutboxJobRegistryException.php:7:final class OutboxJobRegistryException extends \DomainException
app/src/Shared/Infrastructure/Exception/InvalidConfigValueException.php:7:final class InvalidConfigValueException extends \InvalidArgumentException
app/src/Shared/Infrastructure/Exception/ConfigMappingException.php:9:final class ConfigMappingException extends \RuntimeException
app/src/Modules/Notifications/Infrastructure/Exception/CentrifugoPresenceException.php:16:final class CentrifugoPresenceException extends \DomainException
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:17:final class MediaProcessorFailedException extends \DomainException
app/src/Shared/Domain/Exception/ForbiddenException.php:7:final class ForbiddenException extends DomainTranslatableException
app/src/Shared/Domain/Exception/ValidationException.php:7:final class ValidationException extends DomainTranslatableException
app/src/Shared/Domain/Exception/NotFoundException.php:7:final class NotFoundException extends DomainTranslatableException
app/src/Shared/Domain/Exception/InvalidDomainValueException.php:7:final class InvalidDomainValueException extends \DomainException
app/src/Shared/Domain/Exception/DomainTranslatableException.php:9:abstract class DomainTranslatableException extends \DomainException implements TranslatableException
app/src/Shared/Domain/Exception/DomainTranslatableException.php:14:    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
app/src/Shared/Domain/Exception/DomainTranslatableException.php:16:        parent::__construct(message: $translationKey, code: $this->statusCode());
app/src/Shared/Domain/Exception/DomainTranslatableException.php:22:    public function translationKey(): string
app/src/Shared/Domain/Exception/DomainTranslatableException.php:24:        return $this->translationKey;
app/src/Shared/Domain/Exception/DomainTranslatableException.php:33:    public function translationDomain(): string
app/src/Shared/Domain/Exception/DomainTranslatableException.php:35:        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
app/src/Shared/Domain/Exception/DomainTranslatableException.php:42:    public function translationParameters(): array
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:16:final class MediaFileServiceFailedException extends \DomainException
app/src/Shared/Domain/Exception/AuthenticationException.php:7:final class AuthenticationException extends DomainTranslatableException

exec
/bin/zsh -lc 'rg -n "expectExceptionMessage|expectExceptionObject|translationKey|unsupported_file_type|mime_not_allowed|conversion_plan_type_mismatch" tests app/src/Shared app/src/Modules | head -200' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Shared/Domain/Exception/DomainTranslatableException.php:14:    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
app/src/Shared/Domain/Exception/DomainTranslatableException.php:16:        parent::__construct(message: $translationKey, code: $this->statusCode());
app/src/Shared/Domain/Exception/DomainTranslatableException.php:22:    public function translationKey(): string
app/src/Shared/Domain/Exception/DomainTranslatableException.php:24:        return $this->translationKey;
app/src/Shared/Domain/Exception/DomainTranslatableException.php:35:        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:39:        $this->expectExceptionMessage('user.defaultAvatarUrl');
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:60:        $this->expectExceptionMessage('media.multipartThresholdBytes');
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:58:        $this->expectExceptionMessage('locale.default');
tests/App/Modules/System/Http/ApiErrorTestController.php:30:            translationKey: 'app.media.unsupported_file_type',
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:149:        // ключ перевода, а не русский текст — getMessage() == ключ, translationKey() == ключ.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:160:            self::assertSame('app.media.not_found', $exception->translationKey());
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:265:        $this->expectExceptionMessage('Количество попыток обработки превышено.');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:309:        $this->expectExceptionMessage('Постоянный файл не имеет даты удаления.');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:351:        $this->expectExceptionMessage('Путь файла слишком длинный.');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:359:        $this->expectExceptionMessage('Путь файла имеет неверный формат.');
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:35:            translationKey: 'app.media.unsupported_file_type',
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:124:                translationKey: 'app.media.unsupported_file_type',
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:133:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:153:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:175:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:129:                translationKey: 'app.media.mime_not_allowed',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:73:        $this->expectExceptionMessage('app.user.email_taken');
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:88:        $this->expectExceptionMessage('app.user.nickname_taken');
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:103:        $this->expectExceptionMessage('app.user.nickname_taken');
tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php:96:        $this->expectExceptionMessage('Количество попыток outbox превышено.');
tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php:140:        $this->expectExceptionMessage('Ошибка outbox имеет неверную длину.');
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php:62:        $this->expectExceptionMessage('payload неверного типа');
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php:70:        $this->expectExceptionMessage('outbox-envelope');
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php:81:        $this->expectExceptionMessage('не JSON-object');
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php:92:        $this->expectExceptionMessage('некорректный transport payload');
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php:102:        $this->expectExceptionMessage('outboxId');
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:79:        $this->expectExceptionMessage('Постоянная ошибка relay.');
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:152:        $this->expectExceptionMessage('Постоянная ошибка relay с настроенным порогом.');
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:98:        $this->expectExceptionMessage('Размер пачки outbox relay должно быть от 1 до 1000.');
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:106:        $this->expectExceptionMessage('Пауза outbox relay в секундах должно быть от 1 до 3600.');
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:46:        $this->expectExceptionMessage('не найдено');
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:62:        $this->expectExceptionMessage('ожидался');
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php:97:        $this->expectExceptionMessage('восстановлено как');
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:20:            translationKey: 'app.media.unsupported_file_type',
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:27:        self::assertSame('app.media.unsupported_file_type', $exception->translationKey());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:28:        self::assertSame('app.media.unsupported_file_type', $exception->getMessage());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:39:        self::assertSame('app.auth.required', $exception->translationKey());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:49:        self::assertSame('app.media.access_denied', $exception->translationKey());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:59:        self::assertSame('app.media.not_found', $exception->translationKey());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:68:        self::assertSame('plainkey', $exception->translationKey());
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php:264:        $this->expectExceptionMessage('Outbox interceptor получил разные outbox-данные в headers и payload.');
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:75:        $this->expectExceptionMessage('Объект не поддерживает запись в базу.');
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:71:        $this->expectExceptionMessage('Не удалось преобразовать раздел конфигурации `cache`');
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:72:        $this->expectExceptionMessage(CacheConfig::class);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:155:        $this->expectExceptionMessage('Непредвиденная ошибка маппинга.');
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:170:        $this->expectExceptionMessage('должен возвращать массив для объекта');
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:185:        $this->expectExceptionMessage('массив со строковыми ключами');
tests/Unit/Shared/Domain/ValueObject/AbstractUuidV7IdTest.php:26:        $this->expectExceptionMessage('Идентификатор должен быть UUID v7.');

codex
Проверил, что ключ ошибки доступен через `translationKey()`, поэтому тесты могут отличить `mime_not_allowed` от `unsupported_file_type`. Это важно для негативов, где оба варианта сейчас дают один и тот же класс исключения.
exec
/bin/zsh -lc "sed -n '1,80p' app/src/Shared/Domain/Exception/DomainTranslatableException.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use GianTiaga\SpiralApiErrors\Exception\TranslatableException;

abstract class DomainTranslatableException extends \DomainException implements TranslatableException
{
    /**
     * @param array<string, string> $translationParameters
     */
    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
    {
        parent::__construct(message: $translationKey, code: $this->statusCode());
    }

    abstract protected function statusCode(): int;

    #[\Override]
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Домен перевода (= файл каталога) выводится из второго сегмента ключа:
     * `app.media.not_found` -> `media`, `app.system.swagger_ui_disabled` -> `system`.
     * Ключи без модульного сегмента переводятся в домене по умолчанию `messages`.
     */
    #[\Override]
    public function translationDomain(): string
    {
        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function translationParameters(): array
    {
        return $this->translationParameters;
    }
}

codex
- [пропущенный шаг] План нормализует MIME только в `MediaTypeResolver`, но `RequestMediaUploadHandler::assertUploadAllowed()` сравнивает `allowedMimeTypes` с исходной строкой через точное `equals()`: `text/markdown;charset=utf-8` распознается как документ, но не пройдет спецификацию с `text/markdown` — сделать общее сравнение базового MIME для спецификации или явно нормализовать обе стороны; добавить тест на этот путь

- [недостающий тест] Цель плана включает `GetMediaUrl`, но тесты проверяют только готовность объекта и наличие файла в `documents/...`, без вызова выдачи URL для документа — добавить тест `GetMediaUrlHandler` или расширить сквозной тест: готовый `Document` отдается по URL для public/private

- [недостающий тест] Негатив `testRejectsDocumentMimeOutsideSpec` может пройти с неправильной причиной отказа, потому что `unsupported_file_type` и `mime_not_allowed` дают один класс `ValidationException` — проверять `translationKey() === 'app.media.mime_not_allowed'`

- [недостающий тест] Негатив для непустого плана документа предлагает проверить только `imagePlan`, хотя `assertDocumentPlan()` должен отвергать `image`, `video` и `audio` списки — сделать набор из трех случаев и проверять ключ `app.media.conversion_plan_type_mismatch`

- [нарушение правил] План просит добавить в комментарий/докблок слова `whitelist` и `macro-enabled`, а `docs/rules.md` запрещает англицизмы в русских комментариях и документации — писать «разрешенный список MIME» и «форматы Office с макросами»

- [скрытый риск] План не добавляет проверку `CheckMediaIsImage` для `Document`, хотя DJVU теперь специально должен стать `Document`, а не `Image`, и этот запрос защищает image-only сценарии вроде аватара — добавить тест, что `CheckMediaIsImage` возвращает `false` для `MediaType::Document`

- [реализуемость] Формулировка «типизированная константа `list<string>`» может привести к неверному PHP-синтаксису, потому что нативно константа типизируется как `array`, а `list<string>` задается только PHPDoc — явно записать в плане `/** @var list<string> */ private const array DOCUMENT_MIME_TYPES = [...]`
tokens used
133 687
- [пропущенный шаг] План нормализует MIME только в `MediaTypeResolver`, но `RequestMediaUploadHandler::assertUploadAllowed()` сравнивает `allowedMimeTypes` с исходной строкой через точное `equals()`: `text/markdown;charset=utf-8` распознается как документ, но не пройдет спецификацию с `text/markdown` — сделать общее сравнение базового MIME для спецификации или явно нормализовать обе стороны; добавить тест на этот путь

- [недостающий тест] Цель плана включает `GetMediaUrl`, но тесты проверяют только готовность объекта и наличие файла в `documents/...`, без вызова выдачи URL для документа — добавить тест `GetMediaUrlHandler` или расширить сквозной тест: готовый `Document` отдается по URL для public/private

- [недостающий тест] Негатив `testRejectsDocumentMimeOutsideSpec` может пройти с неправильной причиной отказа, потому что `unsupported_file_type` и `mime_not_allowed` дают один класс `ValidationException` — проверять `translationKey() === 'app.media.mime_not_allowed'`

- [недостающий тест] Негатив для непустого плана документа предлагает проверить только `imagePlan`, хотя `assertDocumentPlan()` должен отвергать `image`, `video` и `audio` списки — сделать набор из трех случаев и проверять ключ `app.media.conversion_plan_type_mismatch`

- [нарушение правил] План просит добавить в комментарий/докблок слова `whitelist` и `macro-enabled`, а `docs/rules.md` запрещает англицизмы в русских комментариях и документации — писать «разрешенный список MIME» и «форматы Office с макросами»

- [скрытый риск] План не добавляет проверку `CheckMediaIsImage` для `Document`, хотя DJVU теперь специально должен стать `Document`, а не `Image`, и этот запрос защищает image-only сценарии вроде аватара — добавить тест, что `CheckMediaIsImage` возвращает `false` для `MediaType::Document`

- [реализуемость] Формулировка «типизированная константа `list<string>`» может привести к неверному PHP-синтаксису, потому что нативно константа типизируется как `array`, а `list<string>` задается только PHPDoc — явно записать в плане `/** @var list<string> */ private const array DOCUMENT_MIME_TYPES = [...]`
