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
session id: 019f13ab-adc0-75d1-9b12-a9b0bbea5f0e
--------
user
Прочитай docs/plans/2026-06-29_16-38_application-config-independence.md, docs/rules.md, docs/arch.md. Это план архитектурного рефактора: убрать из arch.md/rules.md исключение, разрешающее TypedConfig в Application; конфиг читать только в Infrastructure (бутлоадеры отдают готовые значения в Application через DI); отрефакторить 4 Application-класса (UserPublicProfileAssembler, SendLoginCodeHandler, CreateUserHandler, RequestMediaUploadHandler) + задача presigned TTL (удалить MediaUrlResolverFactory, дефолт TTL из бутлоадера). Глубоко проверь по реальному коду: открой бутлоадеры (Media/Auth/App/Kernel), 4 класса и их тесты, MediaUrlService/Factory/Resolver, LocaleMiddleware, конфиги, Makefile/coverage-гейт. Критическое ревью строго на русском: пропущенные шаги, неучтённые зависимости и тесты, нарушения docs/rules.md и docs/arch.md, скрытые риски, риски для смежного кода, фазы, которые не пройдут make test, шаги без критериев готовности. План уже прошёл внутреннее мета-ревью тремя моделями (раздел в конце файла) — не повторяй уже учтённое, ищи НОВОЕ. Формат: список строк '- [тип] описание — что предлагаешь'. В конце 'ВЕРДИКТ: <готов/нужны правки> — причина'.
codex
Сначала сверю сам план и обязательные документы, потом пройду по реальным классам, бутлоадерам, конфигам и тестам. Код менять не буду: задача выглядит как ревью плана, а не реализация.
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-29_16-38_application-config-independence.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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

### Исключение: `Media` — foundational-модуль

`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
модулю. Поэтому для него действует осознанное исключение из правила «модули общаются только через
`Application`»: другим модулям разрешено **держать ORM-relation на сущности `Media` (на чтение)** и
**передавать загруженную сущность `Media` в Application-сервисы `Media`**.

Зачем: чтение списков с вложениями (лента `Posts`) должно грузить медиа и их конверсии вместе с
основной выборкой (`->load('media.imageConversions'...)`), а не разрешать URL поэлементно (N+1).
Для этого `PostMedia` объявляет `#[BelongsTo(target: Media::class, ..., cascade: false, fkCreate:
false)]` и eager-грузит её в репозитории, после чего `MediaUrlService::getUrls(Media $media)` строит
URL без обращений в БД.

Что по-прежнему **запрещено** даже для `Media`: использовать `MediaRepository` или `Media/Infrastructure`
из другого модуля; писать/менять данные `Media` через relation (поэтому `cascade: false`); заводить
кросс-модульный FK ради такой связи (`fkCreate: false` — FK либо уже есть в миграции, либо его нет).
Запись и изменение медиа идут только через Command-сценарии `Media/Application`.

`Media` должен давать только свои сценарии:

```text
CreateMedia
DeleteMedia
CheckMediaExists
CheckMediaIsImage
FindMediaUrl
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

 succeeded in 0ms:
---
title: Application не зависит от конфига — чтение TypedConfig только в Infrastructure (+ задача про presigned TTL)
date: 2026-06-29 16:38
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
  research: —
---

# План реализации

## Задача

Сделать правило строгим: слой **Application** (Handler-ы, Application-сервисы, ассемблеры) не зависит от
типизированных конфигов `App\Shared\Infrastructure\Configuration\*Config`. Конфиг читается только в
**Infrastructure** (бутлоадеры, инфра-сервисы, middleware), которая передаёт в Application уже готовые
значения (VO, скаляры, маленькие settings-объекты, доменные сервисы) через DI-биндинги.

В рамках этого же изменения выполняется исходная прикладная задача: срок presigned-ссылки скачивания
медиа получает значение по умолчанию из конфига и опциональное переопределение, а тонкая прослойка
`MediaUrlResolverFactory` удаляется (сворачивается в `MediaUrlService`).

Готово, когда: ни один класс под `Modules/*/Application/**` не импортирует `*Config`; `arch.md` и
`rules.md` описывают строгое правило без прежнего исключения; все затронутые сценарии работают;
`make test` и `make phpstan` зелёные.

## Контекст

- Сегодня `arch.md` (раздел про `TypedConfig`, строки ~285–300) содержит **осознанное исключение**:
  `TypedConfig` можно инжектить прямо в Application-Handler (пример — `MediaConfig` в
  `RequestMediaUploadHandler`). `rules.md` (строка 62, «`env()` только в конфигах») это закрепляет:
  «Сервисы, Handler-ы… получают значения через типизированные конфиг-объекты… или через DI». Пользователь
  решил убрать это исключение и сделать правило строгим (Application не знает про конфиг). `env()` —
  по-прежнему только в `app/config/*.php` (это правило остаётся).
- Инвентаризация показала **4 Application-класса**, нарушающих новое правило:
  - `User/Application/Profile/UserPublicProfileAssembler` ← `UserConfig` (1 значение: `defaultAvatarUrl`).
  - `Auth/Application/Command/SendLoginCode/SendLoginCodeHandler` ← `LocaleConfig` (`supported` + `default`).
  - `User/Application/Command/CreateUser/CreateUserHandler` ← `LocaleConfig` (`supported` + `default`).
  - `Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler` ← `MediaConfig`
    (`stagingTtlSeconds`, `multipartThresholdBytes`, `multipartPartSizeBytes`).
  Плюс исходная задача добавила бы 5-й (`MediaUrlService` ← `MediaConfig`) — её делаем сразу новым способом.
- Логика разбора локали **продублирована** в `SendLoginCodeHandler` и `CreateUserHandler`
  (`in_array($locale, supported) ? $locale : default`). Строгий рефактор заодно убирает дубль.
- Слой Infrastructure читает конфиг законно и **не трогается**: `Media/Infrastructure/FileService/*`
  (S3, Imagick, ffmpeg), `Notifications/Infrastructure/*`, `Outbox/Infrastructure/*`,
  `Shared/Infrastructure/Framework/*` (включая `LocaleMiddleware`, который сам резолвит локаль из
  `Accept-Language` против `LocaleConfig` — остаётся как есть).
- **Presentation вне охвата** (решение пользователя): `System/Presentation/Console/OpenApiGenerateCommand`,
  `HealthController`, `SwaggerController` сейчас тоже инжектят конфиг — их в этой задаче не трогаем
  (возможная отдельная задача).
- Механика DI (из карты кода):
  - `ConfigBootloader` авто-регистрирует каждый `*Config` как singleton по имени класса (через
    `ConfigMapper`). Значит фабрика-замыкание в бутлоадере может принять нужный `*Config` параметром, и
    контейнер его подставит — это законное чтение конфига в Infrastructure.
  - Стили биндинга в проекте: `const BINDINGS` (alias), `const SINGLETONS`, и `defineSingletons(): array`
    с фабрикой-замыканием/ссылкой на метод (`X::class => [self::class, 'makeX']`). Для «прочитать конфиг
    и собрать готовое значение» используется именно фабрика в `defineSingletons()`.
  - Модульные бутлоадеры: `MediaBootloader` (есть, только `BINDINGS`), `AuthBootloader` (есть, только
    `BINDINGS`), `AppBootloader` (Shared, есть `defineSingletons()`). Бутлоадера **User нет** — создаём.
  - Модульные бутлоадеры регистрируются в `Shared/Infrastructure/Framework/Kernel.php::defineBootloaders()`.
- Исходная задача про presigned TTL: `MediaUrlResolverFactory::forMedia(visibility, ttl)` ветвится по
  `Media->visibility`; для public TTL не используется и не валидируется, для private строится
  `MediaPresignedTtl` (диапазон `1..604_800`, бросает `InvalidDomainValueException` 500 вне диапазона) и
  единый `expiresAt` на весь набор URL. Оба места показа URL идут через `MediaUrlService::getUrls`
  (`FindMediaUrlHandler` для аватара, `PostViewAssembler` для ленты — последний по foundational-Media
  исключению зовёт сервис напрямую).

## Принятые решения

- **Новое правило (строгое):** Application не импортирует и не инжектит `*Config`. Конфиг читают только
  Infrastructure-классы (бутлоадеры/инфра-сервисы/middleware). Бутлоадер читает `*Config` в фабрике и
  отдаёт в Application готовое значение через DI. `env()` — только в `app/config/*.php` (без изменений).
- **Охват — только Application** (решение пользователя). Presentation и Infrastructure не трогаем.
- **Паттерн передачи значений** (как Infrastructure отдаёт готовое в Application):
  - *Общая логика над значениями, переиспользуемая модулями* → доменный сервис, собранный фабрикой
    бутлоадера. Локаль → новый `App\Shared\Domain\Locale\LocaleResolver` (держит `supported` + `default`,
    метод `resolve(string): string`), биндится в `AppBootloader`. Оба хендлера зависят от него — уходит и
    конфиг, и дублирование.
  - *Связка нескольких значений для одного класса* → маленький неизменяемый settings-объект в Application
    модуля, собранный фабрикой бутлоадера. Загрузка медиа → `App\Modules\Media\Application\Dto\MediaUploadSettings`
    (3 поля), биндится в `MediaBootloader`, авто-вайрится в `RequestMediaUploadHandler`.
  - *Одно значение для одного потребителя* → биндим сам потребитель фабрикой бутлоадера, передавая готовое
    значение. Аватар по умолчанию → `UserPublicProfileAssembler` биндится в новом `UserBootloader`
    (передаём `string $defaultAvatarUrl`). Срок presigned по умолчанию → `MediaUrlService` биндится в
    `MediaBootloader` (передаём VO `MediaPresignedTtl`).
- **`LocaleResolver` — доменный сервис**, не Entity: держать `list<string> $supported` и `string $default`
  допустимо (правило «без примитивов» — про Entity, не про доменный сервис). Возвращает `string`
  (код локали), как сейчас, чтобы не менять поведение потребителей. Размещение — `Shared/Domain/Locale`,
  потому что общий для Auth и User.
- **`MediaUploadSettings`** — осознанная «проекция только нужного»: держит ровно 3 значения, которые
  использует `RequestMediaUploadHandler`, а не весь `MediaConfig`. Application не знает, что это из конфига.
- **Исходная задача про presigned TTL делается новым способом:** `MediaUrlService` получает готовый
  `MediaPresignedTtl` (значение по умолчанию) из `MediaBootloader`, а не `MediaConfig`. `MediaUrlResolverFactory`
  удаляется, логика выбора стратегии и единый `expiresAt` переезжают в приватный метод сервиса. Параметр
  переопределения `?int $presignedTtlSeconds = null` появляется у `FindMediaUrlQuery` и `MediaUrlService::getUrls`.
  Сборка `MediaPresignedTtl::fromInt(config)` в фабрике `MediaBootloader` ленивая: проверка диапазона
  срабатывает **при первом резолве `MediaUrlService`** (singleton строится один раз), а не на каждый URL и
  не на boot. Если понадобится именно boot-time fail-fast — отдельная задача с eager-валидацией.
- **Строгая семантика переопределения TTL (`!== null`, не truthy):** в `resolverFor` использовать
  `$presignedTtlSeconds !== null ? MediaPresignedTtl::fromInt($presignedTtlSeconds) : $this->defaultPresignedTtl`.
  Нельзя писать `$presignedTtlSeconds ? ...` — `0` falsy и тихо ушёл бы в значение по умолчанию, тогда как
  сейчас явный `0` для приватного медиа бросает `InvalidDomainValueException` (MIN=1). Сохраняем это:
  `null` → значение по умолчанию; явный `0`/вне диапазона → исключение.
- **`MediaUrlService` становится singleton** (его биндит фабрика бутлоадера). Класс `final readonly` без
  состояния — безопасно, но инвариант обязателен: `expiresAt` вычисляется внутри `getUrls`/`resolverFor`
  на каждый вызов, в конструкторе хранится только `MediaPresignedTtl $defaultPresignedTtl`. Иначе все наборы
  получили бы замороженный срок. Тест «private без override → now + значение по умолчанию» это страхует.
- **`LocaleResolver.resolve` возвращает `string`** (код локали), хотя рядом есть enum
  `App\Shared\Domain\Enum\Locale` (правило «Enum вместо строк»). Выбор осознанный: `translator->setLocale`
  принимает строку, а `CreateUserHandler` уже делает `Locale::from(...)` поверх результата — он сохраняется
  как `Locale::from($this->localeResolver->resolve(...))`. Возврат `Locale` потребовал бы трогать оба
  потребителя без выигрыша; фиксируем `string` явно.
- **Язык:** все новые/правленые комментарии, докблоки, `README.md`, `.env.sample` — без англицизма
  «дефолт», только «значение по умолчанию» (правило `docs/rules.md` «Без англицизмов»). Технические
  идентификаторы остаются на английском.
- **Ключ конфига для TTL:** `media.presignedTtlSeconds`, источник `\max(1, (int) \env('MEDIA_PRESIGNED_TTL_SECONDS', 3600))`
  (sane-default нижней границы в стиле соседних полей `media.php`; верхнюю границу и доменный диапазон
  держит `MediaPresignedTtl`).

Ожидаемый объём: ~30 файлов. Новые классы (3): `LocaleResolver`, `MediaUploadSettings`, `UserBootloader`.
Правка прод-кода/доков: `arch.md`, `rules.md`, `Kernel.php`, `AppBootloader`, `MediaBootloader`,
2 locale-хендлера, `RequestMediaUploadHandler`, `UserPublicProfileAssembler`, `MediaUrlService` (+ удаление
`MediaUrlResolverFactory`, докблок `MediaUrlResolver`, докблок `MediaFileServiceContract`),
`FindMediaUrlQuery`, `FindMediaUrlHandler`, `PostViewAssembler`, `app/config/media.php`, `MediaConfig`,
`app/src/Modules/Media/README.md`, `.env.sample`. Тесты: `SendLoginCodeHandlerTest`, `SendLoginCodeJobTest`,
`AuthApplicationTestCase`, `CreateUserHandlerTest`, `UserApplicationTestCase`, `RequestMediaUploadHandlerTest`,
`MediaConfigTest`, `FindMediaUrlHandlerTest`, 3 Unit Infrastructure-теста (`FfmpegWaveformTest`,
`ImagickMediaImageProcessorTest`, `FfmpegMediaProcessorTest`) + новые: юнит `LocaleResolver` и Kernel-тест
биндинга `MediaUploadSettings`.

## Целевой алгоритм

```text
Старт приложения:
  Kernel.defineBootloaders() -> [..., MediaBootloader, AuthBootloader, UserBootloader(новый), ...]
  AppBootloader.defineSingletons():    LocaleResolver  <- LocaleConfig (Infrastructure читает конфиг)
  MediaBootloader.defineSingletons():  MediaUploadSettings <- MediaConfig
                                       MediaUrlService     <- MediaFileServiceContract + MediaPresignedTtl(MediaConfig)
  UserBootloader.defineSingletons():   UserPublicProfileAssembler <- FindMediaUrlHandler + UserConfig.defaultAvatarUrl

Рантайм (Application больше не видит конфиг):
  SendLoginCodeHandler / CreateUserHandler -> LocaleResolver.resolve(locale) -> код локали
  RequestMediaUploadHandler -> MediaUploadSettings.{stagingTtlSeconds, multipartThresholdBytes, multipartPartSizeBytes}
  UserPublicProfileAssembler -> $this->defaultAvatarUrl (строка, не конфиг)
  FindMediaUrlHandler / PostViewAssembler -> MediaUrlService.getUrls(media, ?int presignedTtlSeconds=null):
       resolver = resolverFor(visibility: media.visibility, presignedTtlSeconds: presignedTtlSeconds)
       resolverFor: public -> прямой URL (TTL не нужен);
                    private -> ttl = presignedTtlSeconds !== null ? MediaPresignedTtl::fromInt(presignedTtlSeconds)
                                                                   : $this->defaultPresignedTtl
                               expiresAt = now + ttl, считается на КАЖДЫЙ вызов -> PresignedMediaUrlResolver
```

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

HTTP-маршруты, Filter, Response, внешние сервисы — не меняются. Меняются внутренние контракты и
DI-биндинги:

```text
НОВЫЕ КЛАССЫ:
  App\Shared\Domain\Locale\LocaleResolver
    __construct(list<string> $supported, string $default); resolve(string $locale): string
  App\Modules\Media\Application\Dto\MediaUploadSettings
    __construct(int $stagingTtlSeconds, int $multipartThresholdBytes, int $multipartPartSizeBytes)
  App\Modules\User\Infrastructure\Bootloader\UserBootloader  (+ регистрация в Kernel)

ИЗМЕНЁННЫЕ КОНСТРУКТОРЫ (убираем *Config, добавляем готовое значение):
  SendLoginCodeHandler(... , LocaleResolver $localeResolver)            // было LocaleConfig
  CreateUserHandler(... , LocaleResolver $localeResolver)               // было LocaleConfig
  RequestMediaUploadHandler(... , MediaUploadSettings $uploadSettings)  // было MediaConfig
  UserPublicProfileAssembler(FindMediaUrlHandler $h, string $defaultAvatarUrl)  // было UserConfig
  MediaUrlService(MediaFileServiceContract $files, MediaPresignedTtl $defaultPresignedTtl)  // было фабрика

ЗАДАЧА TTL:
  MediaConfig: + public int $presignedTtlSeconds (последним параметром)
  MediaUrlService::getUrls(Media $media, ?int $presignedTtlSeconds = null): MediaUrlsResult|null
  FindMediaUrlQuery::__construct(string $mediaId, ?int $presignedTtlSeconds = null)
  УДАЛЯЕТСЯ: App\Modules\Media\Application\Service\MediaUrlResolverFactory

БИНДИНГИ:
  AppBootloader.defineSingletons():    LocaleResolver
  MediaBootloader.defineSingletons():  MediaUploadSettings, MediaUrlService
  UserBootloader.defineSingletons():   UserPublicProfileAssembler
```

## Фазы выполнения

### 1. Переписать рамку: arch.md + rules.md

Цель: правило зафиксировано как строгое, последующие фазы приводят код в соответствие.

Что сделать:
- `docs/arch.md` (раздел про `TypedConfig`, ~285–300): убрать «осознанное исключение» про инъекцию
  `TypedConfig` в Application-Handler. Записать правило, **сформулированное про Application/Domain** (не
  «только Infrastructure»), чтобы не сделать незатронутый Presentation доковым нарушителем: «**Domain и
  Application не зависят от `Shared/Infrastructure/Configuration` и не импортируют `*Config`.** Конфиг
  читается в Infrastructure (бутлоадеры/инфра-сервисы/middleware), которая отдаёт в Application готовые
  значения (VO/скаляры/доменные сервисы/settings) через DI-биндинги». Сохранить, что технический сервис с
  поведением по-прежнему идёт через `Application/Contract` + реализацию в `Infrastructure`. Обновить
  пример: вместо «`MediaConfig` в `RequestMediaUploadHandler`» — «бутлоадер собирает готовое значение и
  биндит его в Application». Добавить краткую оговорку: некоторые Presentation-адаптеры (`HealthController`,
  `SwaggerController`, `OpenApiGenerateCommand`) пока читают `*Config` напрямую — это вне охвата текущей
  задачи и возможная отдельная чистка; правило адресовано Application/Domain.
- `docs/rules.md` (строка 62, «`env()` только в конфигах»): переформулировать вторую фразу. Было:
  «Сервисы, Handler-ы и любой другой код получают значения через типизированные конфиг-объекты или через
  DI». Стало: «`env()` — только в `app/config/*.php`; типизированные конфиги (`*Config`) читаются только в
  Infrastructure (бутлоадеры/инфра-сервисы); **Domain и Application `*Config` не импортируют** и получают
  готовые значения (VO/скаляры/доменные сервисы/settings) через DI».

Результат: документы описывают строгое правило; противоречий между ними нет.

Сценарии тестирования: не применимо (правка документации).

Проверка:
- Ручная сверка: в `arch.md`/`rules.md` нет фраз, легализующих `*Config` в Application.
- `make phpstan` зелёный (код ещё не менялся — фаза только про доки).

### 2. Локаль: LocaleResolver + убрать LocaleConfig из двух хендлеров

Цель: разбор локали — общий доменный сервис; конфиг уходит из Auth/User Application; дубль устранён.

Что сделать:
- Создать `App\Shared\Domain\Locale\LocaleResolver`: `__construct(private array $supported, private string $default)`
  (PHPDoc `list<string>` на `$supported`), метод `resolve(string $locale): string` — `in_array($locale,
  $supported, strict: true) ? $locale : $default`. Докблок: доменный сервис, значения приходят готовыми
  из Infrastructure. Возврат `string` — осознанно (см. «Принятые решения»).
- В `AppBootloader.defineSingletons()` добавить фабрику: `LocaleResolver::class => [self::class, 'localeResolver']`,
  метод `protected static function localeResolver(LocaleConfig $localeConfig): LocaleResolver` (метод
  `static` — он не использует `$this`, как `domainCore` у `DomainBootloader`) строит резолвер из полей конфига.
- `SendLoginCodeHandler`: заменить зависимость `LocaleConfig` на `LocaleResolver`; `resolveLocale()`
  делегирует в `$this->localeResolver->resolve(...)`; убрать импорт `LocaleConfig`.
- `CreateUserHandler`: то же; убрать дублирующую логику и импорт `LocaleConfig`; сохранить
  `Locale::from($this->localeResolver->resolve(...))`.

Не трогаем: `LocaleMiddleware` (Infrastructure, читает `LocaleConfig` законно) и его тест
`tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php` (строит `LocaleConfig`
напрямую — это про middleware, не про наши хендлеры).

Результат: оба хендлера не знают про конфиг и не дублируют разбор локали; поведение прежнее.

Сценарии тестирования:
- Поддерживаемая локаль возвращается как есть; неподдерживаемая → `default` (для обоих хендлеров).
- `LocaleResolver` в изоляции: `resolve('ru')='ru'`, `resolve('xx')=default`, граничные (пустая строка → default).

Проверка — обновить ВСЕ места, строящие эти два хендлера с `localeConfig:` (полный список из грепа):
- `tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php` (~стр. 55): `localeConfig: get(LocaleConfig)`
  → `localeResolver: get(LocaleResolver)`.

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
- **`mapToList()` вместо `\array_values(...->all())`**: чтобы получить `list<T>` из коллекции с преобразованием элемента, вызывай метод базовой коллекции `App\Shared\Domain\Collection\TypedCollection::mapToList($fn)`, а не пиши вручную `\array_values($coll->toBase()->map($fn)->all())` или `\array_values(\array_map($fn, $coll->all()))`. Метод коллекции `->all()` PHPStan видит как `array<int, T>`, а не `list<T>`, поэтому ручная связка тянет за собой `array_values`; `mapToList()` прячет это в одном месте и сразу даёт `list<TNew>`. Для переиндексации коллекции, остающейся коллекцией, — её метод `->values()`. `mapToList()` сбрасывает ключи — **не применять на коллекциях-картах** (`TagTextCollection` и подобных `<string, …>`), где ключ несёт смысл: для них конвейер `->toBase()->keyBy($fn)->map($fn)` и оборачивание в нужную коллекцию. `\array_values(...)` допустим только вне темы коллекций: спред id-списка в variadic-метод, `\array_values(\array_unique($list))` над плоским `list<string>`, и конвейер с промежуточной переиндексирующей операцией между `map` и `all` (`->unique()`) как граница `list<T>`, которую `mapToList()` не выражает.
- **Cursor-пагинация — через общие примитивы, не дублированием**: схему «взять `limit + 1`, понять, есть ли следующая страница, отдать первые `limit` и курсор последней отданной» не дублировать в каждом репозитории и обработчике. Используются два общих примитива:
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
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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

codex
План уже содержит много деталей, поэтому дальше проверяю именно места, где он может расходиться с текущим кодом: реальные сигнатуры, регистрацию бутлоадеров и то, как тесты собирают зависимости.
exec
/bin/zsh -lc "sed -n '260,420p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
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
Repository     -> Domain, Cycle ORM
Infrastructure -> Contract своего модуля, framework/runtime libraries, external libraries
Domain         -> PHP standard library, свой Domain, Shared/Domain
Shared         -> общий доменный и инфраструктурный код без привязки к одному модулю
```

Осознанное исключение: типизированный `TypedConfig` из
`Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler,
когда сценарию нужны инфра-дефолты (staging-TTL, пороги, размеры, драйвер). Пример —
`MediaConfig` в `RequestMediaUploadHandler` модуля `Media`.
Формально `Shared/Infrastructure` не входит в список зависимостей Application выше, но
`TypedConfig` — это не технический сервис с поведением и не зависимость от чужого модуля:
правила (`rules.md` «Typed config для каждого config-файла») и эта же `arch.md`
(«Configuration → app/config → Shared/Infrastructure/Configuration») предписывают единое
размещение всех config-DTO в `Shared/Infrastructure/Configuration`. Оборачивать такой
config-DTO в Application-`*Contract` и привязывать его в бутлоадере означало бы создать
pass-through-обёртку над `TypedConfig` ради формального списка — это запрещённый паттерн
(ср. «Без pass-through typecast-обёрток») и сделало бы модуль единственным, кто прячет
собственный typed-config за контрактом. Поэтому прямая инъекция `TypedConfig` в Application
допускается явно. Если Application нужен именно технический сервис с поведением (S3,
процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию
в `Infrastructure`, без исключений.

## Взаимодействие слоёв

### Поток HTTP-команды

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Command DTO
        -> CommandBus::dispatch(command, handler)
          -> Handler::handle(Command)
            -> Modules/{Module}/Domain ValueObject
            -> Modules/{Module}/Domain Entity
            -> Modules/{Module}/Repository
            -> EntityManager::run()
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

### Поток HTTP-запроса

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Query DTO
        -> QueryBus::dispatch(query, handler)
          -> Handler::handle(Query)
            -> Modules/{Module}/Repository read method
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

## API-документация

API-документация генерируется автоматически из типизированного HTTP-слоя:
контроллеров, Filter DTO, Response DTO, Resource-классов, enum-ов и API
attributes в `Modules/{Module}/Presentation/Http`. OpenAPI-спецификация строится из кода, а
Swagger используется как UI для её просмотра.

Генератор OpenAPI живёт в переносимом Composer-пакете `packages/spiral-openapi` с
namespace `GianTiaga\SpiralOpenApi`. Приложение не содержит логики статического разбора:
оно только собирает типизированный `OpenApiConfig`, задаёт mapping базовых
response wrappers и вызывает пакет через команду `openapi:generate`. YAML
записывается в `public/openapi/openapi.yml`, а Swagger UI по `/api/docs` читает
тот же файл через route `/api/docs/openapi.yml`.

Генератор сканирует все модули приложения: `openapi.sourcePath` = `app/src/Modules`,
`openapi.apiNamespace` = `App\Modules`. Он отбирает классы по префиксу namespace и строит
операции только из публичных методов с атрибутом `#[Route]`. Поэтому любой модуль с
`#[Route]`-контроллером автоматически попадает в публичную спецификацию. Если контроллер не
должен публиковаться (внутренний/служебный эндпоинт), его исключают через
`#[OpenApi(ignore: true)]`, а не через сужение области сканирования.

`packages/spiral-openapi` использует Spiral translator только для стандартных описаний
response по ключам `gian_tiaga.spiral_openapi.successful_response` и
`gian_tiaga.spiral_openapi.api_error`. Описания из `#[OpenApi(description: ...)]` и
PHPDoc остаются текстом приложения. YAML статический, поэтому язык выбирается во
время команды генерации.

## API-ошибки

Общая обработка API-ошибок живёт в переносимом Composer-пакете
`packages/spiral-api-errors` с namespace `GianTiaga\SpiralApiErrors`. Пакет зависит от
`packages/spiral-openapi`, потому что возвращает `ErrorResponse` и
`ValidationErrorResponse`.

Общие доменные исключения остаются в приложении в `App\Shared\Domain\Exception` и
расширяют `\DomainException`. Переводимые 4xx (`NotFoundException`, `ForbiddenException`,
`ValidationException`, `AuthenticationException`) расширяют его через общий абстрактный
`DomainTranslatableException`, который реализует
`GianTiaga\SpiralApiErrors\Exception\TranslatableException` и несёт ключ перевода с
параметрами вместо готовой строки. `InvalidDomainValueException` (500) переводимым не
является. Ожидаемые клиентские ошибки с кодами 4xx
`GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` превращает в JSON
`{"message":"...","code":...}` без логирования; для `TranslatableException` сообщение
переводится на границе в локали текущего запроса (`translationKey()` в домене
`translationDomain()`), иначе берётся `getMessage()`. Каталоги переводов приложения
разбиты по модулям: `translationDomain()` выводит домен (= файл `app/locale/{lang}/{domain}.php`)
из второго сегмента ключа — `app.media.*` → `media.php`, `app.system.*` → `system.php`,
ключи без модульного сегмента — домен `messages`. Доменные исключения без
поддерживаемого 4xx-кода, включая `InvalidDomainValueException`, считаются
внутренними ошибками: HTTP-ответ получает обычное сообщение 500, а исходное
сообщение исключения пользователю не отдаётся. Ошибки Spiral Filter рендерятся через
`GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer` по ключу
`gian_tiaga.spiral_api_errors.validation_error` в JSON
`{"message":"Validation error","code":422,"errors":[...]}` или
`{"message":"Ошибка валидации","code":422,"errors":[...]}` в зависимости от
текущего locale.

`ApiExceptionInterceptor` работает только внутри цепочки controller/action.
Ненайденные маршруты возникают раньше controller/action, поэтому их обрабатывает
`GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware` в глобальной HTTP-цепочке.
Он переводит ключ `gian_tiaga.spiral_api_errors.route_not_found` и возвращает JSON
`{"message":"Route not found.","code":404}` или
`{"message":"Маршрут не найден.","code":404}` в зависимости от текущего locale.
Ошибки bootstrap и middleware, не связанные с router 404, остаются в зоне
стандартного Spiral error handler.

`packages/spiral-api-errors` и `packages/spiral-openapi` только читают текущий locale Spiral
translator. Выбор языка пользователя реализован в request-слое приложения:
`App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware` определяет локаль запроса
из заголовка `Accept-Language`, пересечённого с белым списком `LocaleConfig.supported`
(иначе `LocaleConfig.default`), и выставляет её в Spiral translator до контроллера и до
`RouteNotFoundMiddleware`. Для не-HTTP контекстов (queue/console/Temporal) per-request
локали нет — там перевод на границе не выполняется, исключение несёт ключ.

### Поток консольной команды

```text
Console command
  -> input arguments/options
  -> Modules/{Module}/Application/Command DTO

exec
/bin/zsh -lc "sed -n '260,620p' docs/plans/2026-06-29_16-38_application-config-independence.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
  → `localeResolver: get(LocaleResolver)`.
- `tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php` (~стр. 74–77): то же.
- `tests/Feature/Modules/User/Application/CreateUserHandlerTest.php` (~стр. 115–120): то же. **Импорт
  `use ...\LocaleConfig` НЕ удалять** — он ещё используется в ассерте (`...->get(LocaleConfig)->default`,
  ~стр. 63–64). Аналогично проверить ассерты в `SendLoginCodeHandlerTest`.
- `tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php` (~стр. 52–57, метод `createUserHandler()`,
  используется в `CompleteRegistrationHandlerTest`): то же.
- Добавить юнит-тест `LocaleResolver` (поддерживаемая/неподдерживаемая/пустая локаль).
- `make phpstan` зелёный; `make test` зелёный.

### 3. User: UserBootloader + убрать UserConfig из ассемблера

Цель: значение по умолчанию аватара приходит готовой строкой; модуль User получает свой бутлоадер.

Что сделать:
- Создать `App\Modules\User\Infrastructure\Bootloader\UserBootloader` с `defineSingletons()`:
  `UserPublicProfileAssembler::class => [self::class, 'userPublicProfileAssembler']`, метод
  `protected static function userPublicProfileAssembler(FindMediaUrlHandler $findMediaUrlHandler, UserConfig $userConfig): UserPublicProfileAssembler`
  (метод `static`, не использует `$this`) передаёт `defaultAvatarUrl: $userConfig->defaultAvatarUrl`.
- Зарегистрировать `UserBootloader` в `Kernel.php::defineBootloaders()` (строки ~144–148, рядом с
  `MediaBootloader`/`AuthBootloader`): импорт + строка в массиве. **Это критерий готовности фазы** — без
  регистрации DI не соберёт ассемблер и `make test` упадёт.
- `UserPublicProfileAssembler`: конструктор `(FindMediaUrlHandler $findMediaUrlHandler, string $defaultAvatarUrl)`;
  заменить `$this->userConfig->defaultAvatarUrl` на `$this->defaultAvatarUrl`; убрать импорт `UserConfig`;
  поправить докблок (значение по умолчанию приходит готовым, без конфига). (Константа `AVATAR_URL_TTL_SECONDS`
  и вызов `FindMediaUrlQuery` правятся отдельно в фазе 5 — файл трогается дважды по разным причинам.)

Результат: ассемблер не знает про конфиг; собирается бутлоадером.

Сценарии тестирования:
- Профиль с доступным аватаром отдаёт URL; недоступный/удалённый аватар → строка значения по умолчанию.

Проверка:
- `tests/Feature/Modules/User/Application/UserApplicationTestCase.php` (~стр. 95): в `profileHandlerAssembler()`
  заменить `userConfig: $this->getContainer()->get(UserConfig::class)` на
  `defaultAvatarUrl: $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl`. Строку 93 (построение
  `MediaUrlService` через `MediaUrlResolverFactory`) НЕ трогаем здесь — она меняется в фазе 5.
- `make phpstan` зелёный; `make test` зелёный (тесты профиля).

### 4. Media upload: MediaUploadSettings + убрать MediaConfig из RequestMediaUploadHandler

Цель: хендлер загрузки получает только нужные 3 значения готовым settings-объектом.

Что сделать:
- Создать `App\Modules\Media\Application\Dto\MediaUploadSettings` (`final readonly`): поля
  `int $stagingTtlSeconds`, `int $multipartThresholdBytes`, `int $multipartPartSizeBytes`.
- В `MediaBootloader` добавить метод `defineSingletons()` (рядом с существующим `const BINDINGS` —
  они совместимы, базовый `Bootloader` хранит их независимо): фабрика
  `MediaUploadSettings::class => [self::class, 'mediaUploadSettings']`, метод
  `protected static function mediaUploadSettings(MediaConfig $mediaConfig): MediaUploadSettings`
  (`static`) собирает объект из 3 полей конфига. **NB:** в фазе 5 этот же `defineSingletons()` будет
  ДОПОЛНЕН фабрикой `MediaUrlService` — там не заменять метод, а добавлять запись.
- `RequestMediaUploadHandler`: заменить зависимость `MediaConfig` на `MediaUploadSettings`; обращения
  `$this->mediaConfig->stagingTtlSeconds/...ThresholdBytes/...PartSizeBytes` → `$this->uploadSettings->...`;
  убрать импорт `MediaConfig`.

Результат: хендлер загрузки не знает про конфиг; берёт ровно нужные значения.

Сценарии тестирования:
- Файл ≥ порога → multipart с корректным размером части; файл < порога → одиночный PUT (существующие
  тесты, параметризованные `threshold`/`partSize`).
- Staging-TTL применяется к `MediaExpiration` (существующие тесты).
- Фабрика `MediaUploadSettings` резолвится из контейнера и отдаёт значения из конфига.

Проверка:
- `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php` (~стр. 221–224): хелпер
  `handler()` строит `new MediaUploadSettings(stagingTtlSeconds: ..., multipartThresholdBytes: $threshold,
  multipartPartSizeBytes: $partSize)` вместо `new MediaConfig(...)`; параметр конструктора `mediaConfig:`
  → `uploadSettings:`. Это убирает одно из мест `new MediaConfig(...)`.
- **Добавить Kernel-тест биндинга** (по образцу `MediaConfigTest::testRealMediaConfigIsMappedFromContainer`):
  `$this->getContainer()->get(MediaUploadSettings::class)` и assert трёх полей. Без этого тело фабрики
  `mediaUploadSettings()` не исполнится (хендлер загрузки нигде не резолвится из контейнера — нет HTTP-входа),
  и `make test-coverage` (гейт 100%) упадёт. Фабрики `MediaUrlService`/`UserPublicProfileAssembler`/`LocaleResolver`
  покрываются существующими HTTP-тестами (лента/профиль/регистрация строят их из контейнера), а у Media upload
  такого пути нет — отсюда явный Kernel-тест.
- `make phpstan` зелёный; `make test` зелёный.

### 5. Media URL: значение по умолчанию presigned TTL + удаление MediaUrlResolverFactory (исходная задача)

Цель: `MediaUrlService` получает готовый `MediaPresignedTtl` из бутлоадера; срок не передаётся каждый раз;
фабрика удалена.

Что сделать (порядок важен — сначала конфиг-файл, потом DTO):
- `app/config/media.php`: **сначала** добавить `'presignedTtlSeconds' => \max(1, (int) \env('MEDIA_PRESIGNED_TTL_SECONDS', 3600))`
  с русским комментарием (срок presigned-ссылки скачивания, диапазон `1..604800`). Переписать докблок
  файла: убрать утверждение, что срока скачивания в конфиге нет.
- `MediaConfig`: **после** правки конфиг-файла добавить `public int $presignedTtlSeconds` последним
  параметром (без значения по умолчанию). Порядок важен: если DTO обновить раньше файла, Valinor не найдёт
  ключ при `testRealMediaConfigIsMappedFromContainer` и тест упадёт посреди фазы.
- `MediaUrlService`: конструктор `(MediaFileServiceContract $mediaFileService, MediaPresignedTtl $defaultPresignedTtl)`;
  `getUrls(Media $media, ?int $presignedTtlSeconds = null)`; приватный
  `resolverFor(visibility: ..., presignedTtlSeconds: ...)` (именованные аргументы) с логикой бывшей
  `forMedia`, где TTL приватной ветки =
  `$presignedTtlSeconds !== null ? MediaPresignedTtl::fromInt($presignedTtlSeconds) : $this->defaultPresignedTtl`
  (строго `!== null`, не truthy — иначе явный `0` тихо ушёл бы в значение по умолчанию). Сохранить
  инварианты: public short-circuit без TTL; `expiresAt` считается на каждый вызов (singleton). Импорты:
  добавить `MediaVisibility`, `MediaPresignedTtl`, `MediaFileServiceContract`; убрать фабрику.
- Удалить `app/src/Modules/Media/Application/Service/MediaUrlResolverFactory.php`.
- Обновить докблок `app/src/Modules/Media/Application/Service/MediaUrlResolver.php` (ссылается на фабрику).
- В `MediaBootloader.defineSingletons()` **ДОПОЛНИТЬ** (не заменять метод из фазы 4) фабрикой
  `MediaUrlService::class => [self::class, 'mediaUrlService']`, метод
  `protected static function mediaUrlService(MediaFileServiceContract $files, MediaConfig $config): MediaUrlService`
  (`static`) строит `new MediaUrlService($files, MediaPresignedTtl::fromInt($config->presignedTtlSeconds))` —
  проверка диапазона срабатывает при первом резолве сервиса (singleton).
- `FindMediaUrlQuery`: `public ?int $presignedTtlSeconds = null`. `FindMediaUrlHandler`: прокинуть в `getUrls`.
- Убрать передачу TTL у вызывающих: `UserPublicProfileAssembler` → `new FindMediaUrlQuery(mediaId: $mediaId)`
  (удалить константу `AVATAR_URL_TTL_SECONDS` И висячий комментарий к ней, строки ~25–26);
  `PostViewAssembler` → `getUrls(media: $postMedia->media)` (удалить `POST_MEDIA_URL_TTL_SECONDS` и его
  комментарий); комментарии — без «дефолт».
- Обновить докблок `MediaFileServiceContract` (строка ~20): срок скачивания теперь значение по умолчанию из
  конфига с возможностью переопределения.
- Обновить `app/src/Modules/Media/README.md`: устаревший контракт `FindMediaUrl(mediaId, presignedTtlSeconds)`,
  упоминание `MediaUrlResolverFactory`, «TTL в конфиге нет».
- `.env.sample`: `MEDIA_PRESIGNED_TTL_SECONDS=3600` с комментарием про диапазон `1..604800`.

Результат: срок presigned по умолчанию — из конфига (через бутлоадер), переопределение опционально; фабрики нет.

Сценарии тестирования:
- Private без TTL: `presignGet` вызывается, `expiresAt` = now + значение по умолчанию;
  `assertEqualsWithDelta((new \DateTimeImmutable('+3600 seconds'))->getTimestamp(), $result->original->expiresAt->getTimestamp(), 5)`.
- Private с override (300): применяется (существующие тесты — это теперь override-путь).
- Private с явным `presignedTtlSeconds: 0` (или вне диапазона): бросает `InvalidDomainValueException`
  (строгая `!== null` семантика; override валидируется `MediaPresignedTtl`). Новый тест.
- Public: `presignGet` не зовётся, прямой URL без срока; явный `0` игнорируется, потому что public-ветка
  не строит TTL (существующий `testIgnoresInvalidTtlForPublicMedia` — обновить комментарий: причина именно
  в public short-circuit, а не в самом значении `0`).
- Не финализированное медиа → `null`.

Проверка:
- `MediaConfigTest`: обновить оба теста маппинга и конструктор (assert `presignedTtlSeconds === 3600`).
- Обновить оставшиеся `new MediaConfig(...)` (после фазы 4 их 4: `MediaConfigTest` + Unit
  `FfmpegWaveformTest`, `ImagickMediaImageProcessorTest`, `FfmpegMediaProcessorTest`) — добавить
  `presignedTtlSeconds:`. (Проверить грепом `new MediaConfig(`, что список полон; интеграционный
  `FfmpegMediaProcessorIntegrationTest` берёт `MediaConfig` из контейнера, его не трогаем.)
- `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php` (~стр. 213): хелпер `handler()` строит
  `new MediaUrlService(mediaFileService: $fileService, defaultPresignedTtl: MediaPresignedTtl::fromInt(3600))`
  (именованные аргументы); добавить тест значения по умолчанию (private без TTL, `assertEqualsWithDelta`) и
  тест private + `0` → исключение.
- `tests/Feature/Modules/User/Application/UserApplicationTestCase.php` (~стр. 93): заменить
  `new MediaUrlService(new MediaUrlResolverFactory($fileService))` на
  `new MediaUrlService(mediaFileService: $fileService, defaultPresignedTtl: MediaPresignedTtl::fromInt(3600))`;
  убрать импорт `MediaUrlResolverFactory`.
- `make phpstan` зелёный; `make test` зелёный.

### 6. Финальная проверка строгости правила

Цель: убедиться, что правило выполнено целиком и нет висячих ссылок.

Что сделать:
- Поиск по репозиторию: ни один файл под `app/src/Modules/*/Application/**` не импортирует
  `App\Shared\Infrastructure\Configuration\` (`grep -rn "use App\\Shared\\Infrastructure\\Configuration" app/src/Modules/*/Application`).
- Нет ссылок на удалённое/старое: `MediaUrlResolverFactory`, `AVATAR_URL_TTL_SECONDS`,
  `POST_MEDIA_URL_TTL_SECONDS`, `FindMediaUrl(mediaId, presignedTtlSeconds)` и тексты «TTL … в конфиге нет».

Результат: правило соблюдено, мёртвых ссылок нет.

Сценарии тестирования: не применимо (проверочная фаза).

Проверка:
- Оба `grep` пустые (кроме самого файла плана).
- `make phpstan` зелёный; `make test` зелёный — вся suite.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза (кроме доковой 1 и проверочной 6) завершается обновлением своих
тестов и `make test` + `make phpstan`. Новые тесты: юнит `LocaleResolver`; Kernel-тест биндинга
`MediaUploadSettings`; значение по умолчанию presigned TTL + private-override-`0`-исключение. 100% покрытие:
удаляемый код (фабрика) уходит со своим путём; фабрики бутлоадеров `MediaUrlService`/`UserPublicProfileAssembler`/`LocaleResolver`
исполняются существующими HTTP-тестами (лента/профиль/регистрация строят их из контейнера), а фабрика
`MediaUploadSettings` — отдельным Kernel-тестом (её потребитель из контейнера не резолвится). Если отчёт
покрытия покажет любую непокрытую фабрику — добавить аналогичный Kernel-тест `get(...)`.

## Логирование

Стратегия: `debug_precise`. Новых логов не добавляем осознанно: рефактор переносит чтение конфига в
бутлоадеры и не вводит новых рантайм-ветвей, которые стоит логировать. Существующие `#[LogOperation]` на
хендлерах сохраняются. Ошибочная конфигурация presigned TTL проявляется как `InvalidDomainValueException`
из `MediaPresignedTtl::fromInt` при первом резолве `MediaUrlService` (singleton строится один раз) — это
явная ошибка инициализации сервиса, отдельного лога не требует.

## Документация и эксплуатация

- `arch.md` и `rules.md` переписаны под строгое правило (фаза 1).
- `app/src/Modules/Media/README.md` и докблок `MediaFileServiceContract` приведены к новому контракту (фаза 5).
- `.env.sample`: добавлена `MEDIA_PRESIGNED_TTL_SECONDS=3600` с комментарием про диапазон `1..604800`.
  Вне диапазона приложение упадёт при первом обращении к `MediaUrlService` (ленивая сборка
  `MediaPresignedTtl` в фабрике бутлоадера) — это намеренно, ошибка конфигурации видна на первом же
  показе ссылок, а не маскируется.
- Новый `UserBootloader` зарегистрирован в `Kernel.php`.
- Миграций и изменения API нет; особых шагов релиза не требуется.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено (критично):** Kernel-тест биндинга `MediaUploadSettings` — иначе тело фабрики не
  исполнится (хендлер загрузки не резолвится из контейнера) и 100%-гейт `make test-coverage` упадёт (opus).
- **+ Добавлено (критично):** в фазу 2 включены два пропущенных ломаемых теста по локали —
  `AuthApplicationTestCase` (строит `CreateUserHandler`) и `SendLoginCodeJobTest`; список construction-сайтов
  сверен грепом и теперь полный (opus, перепроверка грепом).
- **~ Изменено (поведение):** строгая семантика переопределения TTL — `!== null`, а не truthy; явный `0`
  для приватного медиа по-прежнему бросает `InvalidDomainValueException`, добавлен тест private+`0` (sonnet).
- **~ Изменено:** «падает на старте» → «при первом резолве `MediaUrlService`» (фабрики `defineSingletons()`
  ленивые) — поправлено в «Принятые решения», «Логирование», «Документация», `.env.sample` (opus).
- **~ Изменено:** текст правила фазы 1 сформулирован про Application/Domain (не «только Infrastructure»),
  с оговоркой про Presentation-адаптеры — иначе arch.md/rules.md сделали бы незатронутый Presentation
  доковым нарушителем (opus).
- **~ Изменено:** фабричные методы бутлоадеров помечены `protected static` (не используют `$this`,
  как `domainCore`); явно указано «ДОПОЛНИТЬ `MediaBootloader.defineSingletons()` в фазе 5, не заменять»;
  зафиксирован порядок «сначала `app/config/media.php`, потом `MediaConfig`» (sonnet).
- **~ Изменено:** уточнены точные строки правок тестов (`UserApplicationTestCase` правится в фазах 3 и 5;
  импорт `LocaleConfig` в `CreateUserHandlerTest` не удалять — он в ассерте); добавлен инвариант
  singleton `MediaUrlService` (expiresAt на каждый вызов) (sonnet, opus).
- **+ Добавлено:** зафиксирован осознанный выбор `LocaleResolver.resolve(): string` при наличии enum
  `Locale`; граничный тест резолвера (opus, haiku).
- **Принято к сведению (без правок):** охват Application = ровно 4 класса (перепроверено грепом);
  совместимость `const BINDINGS` + `defineSingletons()`; место `UserBootloader` в `Kernel` —
  подтверждены кодовым ревью (sonnet, opus).
- **Отклонено:** дробить фазу 5 на под-фазы и гонять модульные `make test` вместо полного — проект
  использует единый `make test`/`make phpstan`, дробление не даёт выгоды (haiku); требование «конфиг=0
  падает на старте» неверно — `\max(1, ...)` поднимает `0` до `1`, падает только `> 604800` (haiku).

exec
/bin/zsh -lc "find app/src -path '*Bootloader*' -type f | sort" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxConsoleBootloader.php
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
app/src/Modules/System/Infrastructure/Bootloader/SystemBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/AnnotationsBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/LoggingBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php

exec
/bin/zsh -lc 'rg -n "class (UserPublicProfileAssembler|SendLoginCodeHandler|CreateUserHandler|RequestMediaUploadHandler|MediaUrlService|MediaUrlResolverFactory|MediaUrlResolver|LocaleMiddleware)|MediaConfig|LocaleConfig|UserConfig|defineBootloaders|MediaBootloader|AuthBootloader|AppBootloader|Kernel" app/src tests app/config Makefile .env.sample' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
Makefile:39:	@echo "[make] Старт цели test-kernel: suite=Kernel, причина=нужен Spiral kernel и container, запуск после reset-test"
Makefile:40:	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash -lc 'bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && vendor/bin/phpunit --testsuite Kernel'
Makefile:51:	@echo "[make] Старт цели test-coverage: suite=Unit,Kernel,Feature, драйвер=PCOV, ParaTest процессов=$(TEST_PARALLEL_PROCESSES)"
tests/App/TestKernel.php:7:use App\Shared\Infrastructure\Framework\Kernel;
tests/App/TestKernel.php:8:use Spiral\Testing\TestableKernelInterface;
tests/App/TestKernel.php:9:use Spiral\Testing\Traits\TestableKernel;
tests/App/TestKernel.php:12:class TestKernel extends Kernel implements TestableKernelInterface
tests/App/TestKernel.php:14:    use TestableKernel;
tests/App/TestKernel.php:17:    public function defineBootloaders(): array
tests/App/TestKernel.php:20:            ...parent::defineBootloaders(),
tests/warmup.php:7:use Tests\App\TestKernel;
tests/warmup.php:32:$kernel = TestKernel::create(
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:29:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:355:    private function mediaConfig(int $ffmpegThreads = 0): MediaConfig
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:357:        return new MediaConfig(
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:11:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:67:        return new ImagickMediaImageProcessor(new MediaConfig(
tests/Kernel/Modules/Auth/AuthBootloaderTest.php:5:namespace Tests\Kernel\Modules\Auth;
tests/Kernel/Modules/Auth/AuthBootloaderTest.php:19:final class AuthBootloaderTest extends TestCase
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:8:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:58:    private function mediaConfig(): MediaConfig
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:60:        return new MediaConfig(
tests/Kernel/Modules/Posts/Notification/NotificationContentBuilderTest.php:5:namespace Tests\Kernel\Modules\Posts\Notification;
tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php:5:namespace Tests\Kernel\Modules\Posts\Notification;
tests/Kernel/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php:5:namespace Tests\Kernel\Modules\Outbox\Presentation\Job;
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Cycle;
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Cycle;
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Framework\Bootloader;
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:8:use Spiral\Boot\AbstractKernel;
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:24:        $kernel = $this->createStub(AbstractKernel::class);
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:25:        // AbstractKernel::__destruct() обращается к $finalizer; у созданного без конструктора
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:27:        // Привязка к приватному API фреймворка (AbstractKernel::$finalizer) — чинить при апгрейде Spiral.
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php:28:        (new \ReflectionProperty(AbstractKernel::class, 'finalizer'))
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:8:use App\Shared\Infrastructure\Configuration\User\UserConfig;
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:17:final class UserConfigTest extends TestCase
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:19:    public function testRealUserConfigIsMappedFromContainer(): void
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:21:        $userConfig = $this->getContainer()->get(UserConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:26:    public function testMapsUserConfigSection(): void
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:30:        ])->map(section: UserConfig::configName(), targetClass: UserConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:32:        self::assertSame('user', UserConfig::configName());
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:41:        new UserConfig(defaultAvatarUrl: '   ');
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:52:            ->willReturnMap([[UserConfig::configName(), $config]]);
tests/Kernel/Shared/Infrastructure/Configuration/PushConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/CentrifugoConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:24:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:357:        $ffmpegBinary = $this->getContainer()->get(MediaConfig::class)->ffmpegBinaryPath;
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:8:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:17:final class MediaConfigTest extends TestCase
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:19:    public function testRealMediaConfigIsMappedFromContainer(): void
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:21:        $mediaConfig = $this->getContainer()->get(MediaConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:33:    public function testMapsMediaConfigSection(): void
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:44:        ])->map(section: MediaConfig::configName(), targetClass: MediaConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:46:        self::assertSame('media', MediaConfig::configName());
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:62:        new MediaConfig(
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:82:            ->willReturnMap([[MediaConfig::configName(), $config]]);
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:13:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:21: * фолбэк) берётся из MediaConfig. Источник оригинала — байты, прочитанные из S3.
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:27:    public function __construct(MediaConfig $mediaConfig)
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:8:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:18:final class LocaleConfigTest extends TestCase
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:20:    public function testRealLocaleConfigIsMappedFromContainer(): void
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:22:        $localeConfig = $this->getContainer()->get(LocaleConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:26:        // default входит в supported (точный маппинг покрыт testMapsLocaleConfigSection через стаб).
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:32:        $localeConfig = $this->getContainer()->get(LocaleConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:43:    public function testMapsLocaleConfigSection(): void
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:48:        ])->map(section: LocaleConfig::configName(), targetClass: LocaleConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:50:        self::assertSame('locale', LocaleConfig::configName());
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:60:        new LocaleConfig(supported: ['ru', 'en'], default: 'de');
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:71:            ->willReturnMap([[LocaleConfig::configName(), $config]]);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:21:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:488:    private function mediaConfig(): MediaConfig
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:490:        return $this->getContainer()->get(MediaConfig::class);
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:9:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:16: * Общая инфраструктура ffmpeg-процессоров видео и аудио: сборка клиента php-ffmpeg из MediaConfig,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:27:        protected readonly MediaConfig $mediaConfig,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:77:     * Сборка и запуск процесса ffmpeg с таймаутом из MediaConfig вынесены в отдельный метод, чтобы
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:7:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:60:        self::assertSame($container->get(LocaleConfig::class)->default, $translatorConfig->locale);
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:5:namespace Tests\Kernel\Shared\Infrastructure\Configuration;
tests/Kernel/DemoTest.php:5:namespace Tests\Kernel;
tests/TestCase.php:11:use Spiral\Testing\TestableKernelInterface;
tests/TestCase.php:14:use Tests\App\TestKernel;
tests/TestCase.php:18:    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
tests/TestCase.php:20:        return TestKernel::create(
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:7:use App\Shared\Infrastructure\Framework\Bootloader\AppBootloader;
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:84:        $interceptors = (new \ReflectionClass(AppBootloader::class))
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:30:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:35:final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:224:            mediaConfig: new MediaConfig(
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:22:final class MediaBootloader extends Bootloader
tests/Feature/Modules/System/Http/LocaleHttpTest.php:7:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Feature/Modules/System/Http/LocaleHttpTest.php:39:        $defaultLocale = $this->getContainer()->get(LocaleConfig::class)->default;
app/src/Modules/Media/Application/Service/MediaUrlService.php:32:final readonly class MediaUrlService
app/src/Modules/Media/Application/Service/MediaUrlResolverFactory.php:17:final readonly class MediaUrlResolverFactory
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:30:use App\Shared\Infrastructure\Configuration\User\UserConfig;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:95:            userConfig: $this->getContainer()->get(UserConfig::class),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:101:        return $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:21:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:26:final class CreateUserHandlerTest extends DatabaseTestCase
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:63:            Locale::from($this->getContainer()->get(LocaleConfig::class)->default),
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:120:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:22:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:27:final readonly class RequestMediaUploadHandler
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:32:        private MediaConfig $mediaConfig,
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:15:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:21:final readonly class CreateUserHandler
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:28:        private LocaleConfig $localeConfig,
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:69:     * Нормализует локаль запроса: неподдерживаемое значение заменяется на LocaleConfig.default,
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:15:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:77:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
app/src/Modules/Media/README.md:78:`uploadMode` (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`;
app/src/Modules/Media/README.md:79:размер части — `MediaConfig.multipartPartSizeBytes` (S3 требует ≥ 5 MiB на часть, кроме последней).
app/src/Modules/Media/README.md:165:регистрируется в `MediaBootloader`; Job — в `app/config/queue.php`
app/src/Modules/Media/README.md:170:- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Configuration/Media`): staging-TTL,
app/src/Modules/Media/README.md:177:  `phpunit.xml`. `MediaConfig` инжектится напрямую в `RequestMediaUploadHandler` — осознанное
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:11:use App\Shared\Infrastructure\Configuration\User\UserConfig;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:16: * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:22:final readonly class UserPublicProfileAssembler
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:30:        private UserConfig $userConfig,
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php:14: * (после поднятия NotificationsBootloader в Kernel). Все виды модуля — один enum PostNotificationType,
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:9:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:14:final class SendLoginCodeHandlerTest extends TestCase
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:55:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:7:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:19:final class LocaleMiddlewareTest extends TestCase
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:27:            localeConfig: new LocaleConfig(supported: ['ru', 'en'], default: 'ru'),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:57:            localeConfig: $this->getContainer()->get(\App\Shared\Infrastructure\Configuration\Locale\LocaleConfig::class),
app/src/Modules/Outbox/README.md:73:Модуль подключается в `App\Shared\Infrastructure\Framework\Kernel`.
app/src/Modules/Outbox/README.md:343:Этот bootloader нужно добавить в `Kernel`.
app/src/Modules/Auth/Infrastructure/Mail/SpiralLoginCodeMailer.php:14: * Presentation/views модуля, namespace `auth` регистрирует AuthBootloader) и выполняется отправка.
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:20:use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:21:use Spiral\Bootloader\Auth\HttpAuthBootloader;
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:32:final class AuthBootloader extends Bootloader
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:51:        return [HttpAuthBootloader::class, SpiralAuthBootloader::class, ViewsBootloader::class];
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:54:    public function init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth, ViewsBootloader $views): void
app/src/Shared/Infrastructure/Framework/Kernel.php:36:use App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader;
app/src/Shared/Infrastructure/Framework/Kernel.php:37:use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
app/src/Shared/Infrastructure/Framework/Kernel.php:47:class Kernel extends \Spiral\Framework\Kernel
app/src/Shared/Infrastructure/Framework/Kernel.php:66:    public function defineBootloaders(): array
app/src/Shared/Infrastructure/Framework/Kernel.php:144:            MediaBootloader::class,
app/src/Shared/Infrastructure/Framework/Kernel.php:145:            AuthBootloader::class,
app/src/Shared/Infrastructure/Framework/Kernel.php:189:    public function defineAppBootloaders(): array
app/src/Shared/Infrastructure/Framework/Kernel.php:193:            Bootloader\AppBootloader::class,
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:7:use Spiral\Boot\AbstractKernel;
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:33:    public function init(AbstractKernel $kernel): void
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php:20:final class AppBootloader extends DomainBootloader
app/src/Modules/Notifications/README.md:394:`NotificationsBootloader` уже зарегистрирован в `App\Shared\Infrastructure\Framework\Kernel`
app/src/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloader.php:40: * конфигурацию Guzzle другим модулям. Регистрируется в Kernel после Outbox-бутлоадеров, т.к. boot()
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:8:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:14: * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:18:final readonly class SendLoginCodeHandler
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:23:        private LocaleConfig $localeConfig,
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php:10:final readonly class MediaConfig implements TypedConfig
app/src/Shared/Infrastructure/Configuration/Locale/LocaleConfig.php:10:final readonly class LocaleConfig implements TypedConfig
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:7:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:27:final readonly class LocaleMiddleware implements MiddlewareInterface
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:31:        private LocaleConfig $localeConfig,
app/src/Shared/Infrastructure/Configuration/User/UserConfig.php:10:final readonly class UserConfig implements TypedConfig

codex
Нашёл одно важное направление проверки: план говорит про `make test`, но в проекте есть отдельная цель покрытия. Сейчас читаю Makefile и конкретные тесты, чтобы понять, какие фабрики реально будут исполнены и где план может недосчитать покрытие.
exec
/bin/zsh -lc "sed -n '1,240p' app/src/Shared/Infrastructure/Framework/Kernel.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework;

use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Bootloader as Framework;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Bootloader\Views\TranslatedCacheBootloader;
use Spiral\Cache\Bootloader\CacheBootloader;
use Spiral\Cycle\Bootloader as CycleBridge;
use Spiral\DataGrid\Bootloader\GridBootloader;
use Spiral\Debug\Bootloader\DumperBootloader;
use Spiral\Distribution\Bootloader\DistributionBootloader;
use Spiral\DotEnv\Bootloader\DotenvBootloader;
use Spiral\Events\Bootloader\EventsBootloader;
use Spiral\League\Event\Bootloader\EventBootloader;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\Prototype\Bootloader\PrototypeBootloader;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Scaffolder\Bootloader\ScaffolderBootloader;
use Spiral\Scheduler\Bootloader\SchedulerBootloader;
use Spiral\SendIt\Bootloader\MailerBootloader;
use Spiral\Sentry\Bootloader\SentryReporterBootloader;
use Spiral\Storage\Bootloader\StorageBootloader;
use Spiral\TemporalBridge\Bootloader as TemporalBridge;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Spiral\Twig\Bootloader\TwigBootloader;
use Spiral\Validation\Bootloader\ValidationBootloader;
use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
use Spiral\Views\Bootloader\ViewsBootloader;
use App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader;
use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
use App\Modules\Notifications\Infrastructure\Bootloader\NotificationsBootloader;
use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
use App\Modules\Posts\Infrastructure\Bootloader\PostsBootloader;
use App\Modules\System\Infrastructure\Bootloader\SystemBootloader;
use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;
use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;

class Kernel extends \Spiral\Framework\Kernel
{
    #[\Override]
    public function defineSystemBootloaders(): array
    {
        return [
            CoreBootloader::class,
            DotenvBootloader::class,

            // До сканирования токенайзера: пометить @attention из докблоков Cycle игнорируемым
            Bootloader\AnnotationsBootloader::class,

            TokenizerListenerBootloader::class,

            DumperBootloader::class,
        ];
    }

    #[\Override]
    public function defineBootloaders(): array
    {
        return [
            // Логирование и обработка исключений
            MonologBootloader::class,
            Bootloader\ExceptionHandlerBootloader::class,

            // Логи приложения
            Bootloader\ConfigBootloader::class,
            Bootloader\LoggingBootloader::class,

            // RoadRunner
            RoadRunnerBridge\LoggerBootloader::class,
            RoadRunnerBridge\QueueBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,
            RoadRunnerBridge\CacheBootloader::class,
            RoadRunnerBridge\LockBootloader::class,

            // Базовые сервисы
            Framework\SnapshotsBootloader::class,

            // Безопасность и валидация
            Framework\Security\EncrypterBootloader::class,
            Framework\Security\FiltersBootloader::class,
            Framework\Security\GuardBootloader::class,

            // HTTP-расширения
            HttpBootloader::class,
            Framework\Http\ErrorHandlerBootloader::class,
            Framework\Http\RouterBootloader::class,
            Framework\Http\JsonPayloadsBootloader::class,
            Framework\Http\CookiesBootloader::class,
            Framework\Http\SessionBootloader::class,
            Framework\Http\CsrfBootloader::class,
            Framework\Http\PaginationBootloader::class,

            // Базы данных
            CycleBridge\DatabaseBootloader::class,
            CycleBridge\MigrationsBootloader::class,

            // ORM
            CycleBridge\SchemaBootloader::class,
            CycleBridge\CycleOrmBootloader::class,
            CycleBridge\AnnotatedBootloader::class,

            // Диспетчер событий
            EventsBootloader::class,
            EventBootloader::class,

            // Планировщик
            SchedulerBootloader::class,

            // Sentry и сборщики данных
            SentryReporterBootloader::class,
            Framework\DebugBootloader::class,
            Framework\Debug\LogCollectorBootloader::class,
            Framework\Debug\HttpCollectorBootloader::class,

            // Представления
            ViewsBootloader::class,
            TwigBootloader::class,

            // Очереди
            QueueBootloader::class,

            // Кэш
            CacheBootloader::class,

            // Хранилище файлов
            StorageBootloader::class,
            DistributionBootloader::class,

            // Интернационализация
            I18nBootloader::class,
            TranslatedCacheBootloader::class,
            OpenApiToolsBootloader::class,
            CqrsBootloader::class,
            OutboxBootloader::class,
            MediaBootloader::class,
            AuthBootloader::class,
            SystemBootloader::class,
            NotificationsBootloader::class,
            PostsBootloader::class,

            // Почта
            MailerBootloader::class,

            // Data Grid
            GridBootloader::class,

            // Temporal
            TemporalBridge\PrototypeBootloader::class,
            TemporalBridge\TemporalBridgeBootloader::class,

            NyholmBootloader::class,

            CycleBridge\DataGridBootloader::class,

            ValidationBootloader::class,
            ValidatorBootloader::class,

            RoadRunnerBridge\MetricsBootloader::class,

            // Консольные команды
            Framework\CommandBootloader::class,
            Bootloader\OpenApiBootloader::class,
            OutboxConsoleBootloader::class,
            RoadRunnerBridge\CommandBootloader::class,
            CycleBridge\CommandBootloader::class,
            ScaffolderBootloader::class,
            RoadRunnerBridge\ScaffolderBootloader::class,
            CycleBridge\ScaffolderBootloader::class,

            // Быстрое прототипирование кода
            PrototypeBootloader::class,

            // Группы маршрутов и middleware
            ApiErrorBootloader::class,
            Bootloader\RoutesBootloader::class,
        ];
    }

    #[\Override]
    public function defineAppBootloaders(): array
    {
        return [
            // Доменный обработчик приложения
            Bootloader\AppBootloader::class,
        ];
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use Spiral\Bootloader\DomainBootloader;
use Spiral\Cycle\Interceptor\CycleInterceptor;
use Spiral\DataGrid\Interceptor\GridInterceptor;
use Spiral\Domain\GuardInterceptor;
use Psr\Clock\ClockInterface;
use Spiral\Interceptors\HandlerInterface;
use Symfony\Component\Clock\NativeClock;
use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

/**
 * @link https://spiral.dev/docs/http-interceptors
 */
final class AppBootloader extends DomainBootloader
{
    protected const array INTERCEPTORS = [
        CycleInterceptor::class,
        GridInterceptor::class,
        GuardInterceptor::class,
        HttpResponseInterceptor::class,
        ApiExceptionInterceptor::class,
    ];

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            HandlerInterface::class => [self::class, 'domainCore'],
            ClockInterface::class => NativeClock::class,
        ];
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Bootloader;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Infrastructure\FileService\ConfiguredS3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
use App\Modules\Media\Infrastructure\FileService\ImagickMediaImageProcessor;
use App\Modules\Media\Infrastructure\FileService\S3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\S3MediaFileService;
use App\Modules\Media\Presentation\Job\ProcessMediaJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Boot\Bootloader\Bootloader;

final class MediaBootloader extends Bootloader
{
    protected const BINDINGS = [
        MediaFileServiceContract::class => S3MediaFileService::class,
        MediaImageProcessorContract::class => ImagickMediaImageProcessor::class,
        MediaVideoProcessorContract::class => FfmpegMediaVideoProcessor::class,
        MediaAudioProcessorContract::class => FfmpegMediaAudioProcessor::class,
        S3ClientProvider::class => ConfiguredS3ClientProvider::class,
    ];

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: MediaUploaded::class,
            outboxJobClass: ProcessMediaJob::class,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,160p' app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Bootloader;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Auth\UserActorProvider;
use App\Modules\Auth\Infrastructure\Hash\HmacSecretHasher;
use App\Modules\Auth\Infrastructure\Mail\SpiralLoginCodeMailer;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Auth\Transport\HeaderTransport;
use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
use Spiral\Bootloader\Auth\HttpAuthBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * Каркас аутентификации модуля Auth. Контракты Application привязаны к инфраструктурным
 * реализациям. Транспорт (Authorization: Bearer), хранилище токенов (cycle) и actor-provider
 * регистрируются кодом без app/config/auth.php. View-шаблоны модуля (например письмо с кодом
 * входа) лежат в Presentation/views и регистрируются под namespace `auth`. Пара
 * LoginCodeRequested → SendLoginCodeJob регистрируется в outbox-реестре.
 */
final class AuthBootloader extends Bootloader
{
    /**
     * Namespace представлений модуля Auth (ссылка на шаблон: `auth:<имя>`).
     */
    public const string VIEW_NAMESPACE = 'auth';

    protected const BINDINGS = [
        SecretHasherContract::class => HmacSecretHasher::class,
        TokenGeneratorContract::class => RandomTokenGenerator::class,
        AuthTokenStorageContract::class => CycleTokenStorage::class,
        LoginCodeMailerContract::class => SpiralLoginCodeMailer::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [HttpAuthBootloader::class, SpiralAuthBootloader::class, ViewsBootloader::class];
    }

    public function init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth, ViewsBootloader $views): void
    {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Presentation/views', \dirname(path: __DIR__, levels: 2)),
        );
        $httpAuth->addTransport(
            name: 'header',
            transport: new HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s'),
        );
        $httpAuth->addTokenStorage(name: 'cycle', storage: CycleTokenStorage::class);
        $auth->addActorProvider(UserActorProvider::class);
    }

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: LoginCodeRequested::class,
            outboxJobClass: SendLoginCodeJob::class,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' Makefile" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
COMPOSE_FILE ?= docker/docker-compose.dev.yml
ENV_FILE ?= .env
PROJECT_NAME ?= yoga-loka-spiral-2
APP_SERVICE ?= app-http
CMD ?= bash
COMPOSE = docker compose -f $(COMPOSE_FILE) --env-file $(ENV_FILE) -p $(PROJECT_NAME)

.PHONY: up down restart composer-install test test-unit test-kernel test-feature test-coverage warmup phpstan qa qa-build shell logs migrate reset-test

up:
	@echo "[make] Старт цели up: project=$(PROJECT_NAME)"
	@$(COMPOSE) up -d --build
	@echo "[make] Цель up завершена"

down:
	@echo "[make] Старт цели down: project=$(PROJECT_NAME)"
	@$(COMPOSE) down
	@echo "[make] Цель down завершена"

restart: down up

composer-install:
	@echo "[make] Старт цели composer-install: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) composer install
	@echo "[make] Цель composer-install завершена"

test: TEST_PARALLEL_PROCESSES = 4
test: reset-test
	@echo "[make] Старт цели test: полный gate, ParaTest процессов=$(TEST_PARALLEL_PROCESSES), один reset и один набор тестов"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner
	@echo "[make] Цель test завершена"

test-unit:
	@echo "[make] Старт цели test-unit: suite=Unit, режим=lightweight, без reset и внешних сервисов"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash -lc 'bash docker/test/assert-unit-suite-is-light.sh && vendor/bin/phpunit --testsuite Unit'
	@echo "[make] Цель test-unit завершена"

test-kernel: reset-test
	@echo "[make] Старт цели test-kernel: suite=Kernel, причина=нужен Spiral kernel и container, запуск после reset-test"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash -lc 'bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && vendor/bin/phpunit --testsuite Kernel'
	@echo "[make] Цель test-kernel завершена"

test-feature: TEST_PARALLEL_PROCESSES = 4
test-feature: reset-test
	@echo "[make] Старт цели test-feature: suite=Feature, ParaTest процессов=$(TEST_PARALLEL_PROCESSES), запуск после reset-test"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash -lc 'bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && vendor/bin/paratest --processes "$${TEST_PARALLEL_PROCESSES:-4}" --testsuite Feature'
	@echo "[make] Цель test-feature завершена"

test-coverage: TEST_PARALLEL_PROCESSES = 4
test-coverage: reset-test
	@echo "[make] Старт цели test-coverage: suite=Unit,Kernel,Feature, драйвер=PCOV, ParaTest процессов=$(TEST_PARALLEL_PROCESSES)"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash -lc 'bash docker/test/clean-run-artifacts.sh && bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && COMPOSER_PROCESS_TIMEOUT=900 composer test-coverage'
	@echo "[make] Цель test-coverage завершена"

warmup:
	@echo "[make] Старт цели warmup: прогрев Cycle schema cache в тестовых runtime-каталогах"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash docker/test/warmup.sh
	@echo "[make] Цель warmup завершена"

phpstan:
	@echo "[make] Старт цели phpstan: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) composer phpstan
	@echo "[make] Цель phpstan завершена"

qa: TEST_PARALLEL_PROCESSES = 4
qa: reset-test
	@echo "[make] Старт цели qa: стиль, PHPStan и один coverage-run (PCOV), процессов=$(TEST_PARALLEL_PROCESSES), без пересборки образа"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/run-qa.sh
	@echo "[make] Цель qa завершена"

qa-build: TEST_PARALLEL_PROCESSES = 4
qa-build: reset-test
	@echo "[make] Старт цели qa-build: пересборка образа + тот же QA, процессов=$(TEST_PARALLEL_PROCESSES)"
	@$(COMPOSE) --profile test run --rm --build -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/run-qa.sh
	@echo "[make] Цель qa-build завершена"

shell:
	@echo "[make] Старт цели shell: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) $(CMD)

logs:
	@echo "[make] Старт цели logs"
	@$(COMPOSE) logs --tail=200
	@echo "[make] Цель logs завершена"

migrate:
	@echo "[make] Старт цели migrate: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) php app.php migrate --force
	@echo "[make] Цель migrate завершена"

reset-test:
	@echo "[make] Старт цели reset-test: процессов=$(TEST_PARALLEL_PROCESSES)"
	@test "$${DB_TEST_DATABASE:-yoga_loka_test}" = "yoga_loka_test"
	@test "$${MINIO_TEST_BUCKET:-yoga-loka-test}" = "yoga-loka-test"
	@$(COMPOSE) up -d postgres redis minio mailpit
	@$(COMPOSE) run --rm postgres-init
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/prepare-parallel-resources.sh
	@$(COMPOSE) run --rm -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) minio-init reset-test
	@echo "[make] Цель reset-test завершена"

codex
По Makefile видно: `make test` не запускает цель покрытия, а `make qa` запускает покрытие. Это может быть отдельным риском плана, потому что сам план опирается на 100% покрытие и местами говорит только про `make test`. Продолжаю сверку по классам и тестам.
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\SendLoginCode;

use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Spiral\Translator\TranslatorInterface;

/**
 * Отправка письма с кодом входа. Выполняется в очереди (per-request локали нет), поэтому язык
 * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
 * неподдерживаемом значении. Тема и тело письма — из переводов домена auth, а сама отправка
 * через framework-mailer инкапсулирована за LoginCodeMailerContract.
 */
final readonly class SendLoginCodeHandler
{
    public function __construct(
        private LoginCodeMailerContract $loginCodeMailer,
        private TranslatorInterface $translator,
        private LocaleConfig $localeConfig,
    ) {}

    #[LogOperation]
    public function handle(SendLoginCodeCommand $command): void
    {
        $locale = $this->resolveLocale($command->locale);

        $this->loginCodeMailer->send(
            email: $command->email,
            subject: $this->translator->trans(
                id: 'app.auth.code_email_subject',
                parameters: [],
                domain: 'auth',
                locale: $locale,
            ),
            body: $this->translator->trans(
                id: 'app.auth.code_email_body',
                parameters: ['code' => $command->code],
                domain: 'auth',
                locale: $locale,
            ),
        );
    }

    private function resolveLocale(string $locale): string
    {
        return \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
            ? $locale
            : $this->localeConfig->default;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservedNicknameRepository $reservedNicknameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private LocaleConfig $localeConfig,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreateUserCommand $command): CreateUserResult
    {
        $email = Email::fromString($command->email);
        $nickname = UserNickname::fromString($command->nickname);

        if ($this->userRepository->existsByEmail($email)) {
            throw new ValidationException('app.user.email_taken');
        }

        if (
            $this->userRepository->existsByNickname($nickname)
            || $this->reservedNicknameRepository->isReserved($nickname)
        ) {
            throw new ValidationException('app.user.nickname_taken');
        }

        $user = User::create(
            name: UserName::fromString($command->name),
            email: $email,
            nickname: $nickname,
            locale: $this->resolveLocale($command->locale),
        );
        $user->confirmEmail();

        $this->entityManager->persist($user);
        $this->entityManager->run();

        $this->logger->debug(message: 'Пользователь создан.', context: [
            'userId' => $user->id->value(),
            'email' => $email->value(),
        ]);

        return new CreateUserResult(userId: $user->id->value());
    }

    /**
     * Нормализует локаль запроса: неподдерживаемое значение заменяется на LocaleConfig.default,
     * чтобы вход вне HTTP-потока (консоль, очередь) не приводил к 500 из-за Locale::from().
     */
    private function resolveLocale(string $locale): Locale
    {
        $supported = \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
            ? $locale
            : $this->localeConfig->default;

        return Locale::from($supported);
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
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Entity\User;
use App\Shared\Infrastructure\Configuration\User\UserConfig;

/**
 * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
 * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
 * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
 *
 * FindMediaUrl вызывается напрямую (а не через QueryBus), потому что это единственный сценарий,
 * возвращающий nullable, и обёртка шины теряет null из вывода типов — прямой вызов сохраняет
 * контракт «медиа недоступно -> null -> дефолт» без try-catch и без подавления статанализа.
 */
final readonly class UserPublicProfileAssembler
{
    // Аватары публичны (прямой URL без срока), поэтому TTL фактически не используется; значение
    // нужно только для приватной ветки FindMediaUrl и берётся техническим дефолтом рядом с местом.
    private const int AVATAR_URL_TTL_SECONDS = 3600;

    public function __construct(
        private FindMediaUrlHandler $findMediaUrlHandler,
        private UserConfig $userConfig,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatarUrl: $this->resolveAvatarUrl($user),
            locale: $user->locale->value,
        );
    }

    private function resolveAvatarUrl(User $user): string
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        $mediaUrls = $this->findMediaUrlHandler->handle(
            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
        );

        // Берём оригинал аватара. Если медиа недоступно (null) или оригинал удалён
        // (readyOriginalRemoved -> original = null) — подставляем значение по умолчанию.
        if ($mediaUrls === null || $mediaUrls->original === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        return $mediaUrls->original->url;
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Service/MediaUrlService.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Строит полный набор URL из УЖЕ загруженного медиа: оригинал (если не удалён) и все конверсии.
 * Конверсии берутся из связей сущности (imageConversions/videoConversions/audioConversions),
 * поэтому метод не делает запросов в БД — при условии, что связи загружены eager заранее
 * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
 * Если связи не загружены, Cycle подгрузит их лениво — это вернёт N+1, поэтому вызывающий обязан
 * передавать медиа с eager-загруженными конверсиями.
 *
 * Не бросает: не финализированное медиа (ещё не ready и не readyOriginalRemoved) -> null. После
 * удаления оригинала original = null, конверсии резолвятся.
 */
final readonly class MediaUrlService
{
    public function __construct(private MediaUrlResolverFactory $resolverFactory) {}

    public function getUrls(Media $media, int $presignedTtlSeconds): MediaUrlsResult|null
    {
        if (!$media->isFinalized()) {
            return null;
        }

        $resolver = $this->resolverFactory->forMedia(
            visibility: $media->visibility,
            presignedTtlSeconds: $presignedTtlSeconds,
        );

        return new MediaUrlsResult(
            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
        );
    }

    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
    {
        $imageUrls = $media->imageConversions->toBase()->map(
            fn(MediaImageConversion $conversion): MediaConversionUrl => $this->conversionUrl(
                kind: MediaType::Image,
                type: $conversion->type,
                storage: $conversion->storage,
                path: $conversion->path,
                resolver: $resolver,
            ),
        );

        $videoUrls = $media->videoConversions->toBase()->map(
            fn(MediaVideoConversion $conversion): MediaConversionUrl => $this->conversionUrl(
                kind: MediaType::Video,
                type: $conversion->type,
                storage: $conversion->storage,
                path: $conversion->path,
                resolver: $resolver,
            ),
        );

        $audioUrls = $media->audioConversions->toBase()->map(
            fn(MediaAudioConversion $conversion): MediaConversionUrl => $this->conversionUrl(
                kind: MediaType::Audio,
                type: $conversion->type,
                storage: $conversion->storage,
                path: $conversion->path,
                resolver: $resolver,
            ),
        );

        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
    }

    private function conversionUrl(
        MediaType $kind,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        MediaStorage $storage,
        MediaPath $path,
        MediaUrlResolver $resolver,
    ): MediaConversionUrl {
        $resolved = $resolver->resolve(storage: $storage, path: $path);

        return new MediaConversionUrl(kind: $kind, type: $type, url: $resolved->url, expiresAt: $resolved->expiresAt);
    }
}

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Service/MediaUrlResolverFactory.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;

/**
 * Создаёт резолвер URL, привязанный к контексту медиа. Это единственное место, где видимость и
 * срок presigned-ссылки превращаются в стратегию построения URL: для public — прямые URL без срока
 * (presignedTtlSeconds не используется и не валидируется), для private — единый срок истечения,
 * общий для оригинала и всех конверсий.
 */
final readonly class MediaUrlResolverFactory
{
    public function __construct(private MediaFileServiceContract $mediaFileService) {}

    public function forMedia(MediaVisibility $visibility, int $presignedTtlSeconds): MediaUrlResolver
    {
        if ($visibility === MediaVisibility::Public) {
            return new PublicMediaUrlResolver($this->mediaFileService);
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
        );

        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
    }
}

codex
Сигнатуры совпадают с основным направлением плана. Теперь проверяю смежные вызовы `MediaUrlService` и конфиги: именно там чаще всего остаются старые обязательные параметры или новые параметры DTO ломают тесты.
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Service/MediaUrlResolver.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,160p' app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Строит URL объекта медиа в зафиксированном контексте одного запроса. Способ (прямой публичный
 * URL или presigned со сроком) и единый срок истечения определяются один раз при создании резолвера
 * через MediaUrlResolverFactory, поэтому вызывающему остаётся передать только пару (storage, path) —
 * одинаково для оригинала и каждой конверсии.
 */
interface MediaUrlResolver
{
    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult;
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт серверных файловых операций модуля Media поверх S3-совместимого хранилища.
 *
 * Реальное имя бакета и prefix реализация резолвит из StorageConfig по алиасу MediaStorage,
 * а не из значения enum напрямую. Срок действия presigned-ссылок задаёт потребитель — через
 * MediaUploadSpec (загрузка) и FindMediaUrlQuery (скачивание), поэтому presign-методы принимают expiresAt.
 */
interface MediaFileServiceContract
{
    /**
     * Presigned PUT-ссылка для прямой одиночной загрузки клиентом.
     */
    public function presignPut(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        \DateTimeImmutable $expiresAt,
    ): string;

    /**
     * Инициирует multipart-загрузку и возвращает её uploadId.
     */
    public function createMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
    ): MediaMultipartUploadIdValue;

    /**
     * Presigned-ссылки на загрузку каждой части (1..partsCount).
     */
    public function presignUploadParts(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        \DateTimeImmutable $expiresAt,
    ): MediaPresignedPartCollection;

    /**
     * Собирает multipart-объект из загруженных частей.
     *
     * На повторе после частичного сбоя S3 может вернуть NoSuchUpload — реализация трактует
     * это как успех, если headObject подтверждает собранный объект.
     */
    public function completeMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartCollection $parts,
    ): void;

    /**
     * Отменяет незавершённую multipart-загрузку. 404/NoSuchUpload игнорируется.
     */
    public function abortMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
    ): void;

    /**
     * Метаданные объекта. null — объекта нет.
     */
    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;

    /**
     * Содержимое объекта (для чтения оригинала перед конверсией).
     */
    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;

    /**
     * Скачивает объект в локальный временный файл и возвращает его путь. Стримовое чтение без
     * полного буфера в памяти (для ffmpeg-обработки видео/аудио). Владелец файла — вызыватель:
     * он обязан удалить его после использования (процессор делает это в finally).
     */
    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;

    /**
     * Заливает локальный файл (результат ffmpeg-обработки) в хранилище стримом, без полного
     * буфера в памяти. Локальный файл не удаляется — его владелец вызыватель.
     */
    public function uploadFromFile(
        MediaStorage $storage,
        MediaPath $path,
        string $localFile,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Заливает объект (результат конверсии) в хранилище.
     */
    public function putObject(
        MediaStorage $storage,
        MediaPath $path,
        string $contents,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Копирует объект между хранилищами/путями (перекладка оригинала upload -> target).
     */
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void;

    /**
     * Удаляет объект. 404 игнорируется (идемпотентное удаление).
     */
    public function deleteObject(MediaStorage $storage, MediaPath $path): void;

    /**
     * Presigned GET-ссылка с TTL (для private-медиа).
     */
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;

    /**
     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
     */
    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
}

exec
/bin/zsh -lc "sed -n '1,160p' app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php && sed -n '1,160p' app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

final readonly class FindMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int $presignedTtlSeconds,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Application\Service\MediaUrlService;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Best-effort разрешение всех URL медиа по его id: оригинал (если не удалён) и все конверсии.
 * Грузит медиа вместе с конверсиями одним набором запросов и делегирует построение URL в
 * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
 * подставил значение по умолчанию (аватар в профиле) без try-catch.
 *
 * Для модулей, которые уже держат сущность Media загруженной (например, лента Posts через relation),
 * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
 */
final readonly class FindMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUrlService $mediaUrlService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
    {
        $media = $this->mediaRepository->findByIdWithConversions(MediaId::fromString($query->mediaId));

        if ($media === null) {
            return null;
        }

        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Posts/Application/Assembler/PostViewAssembler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 1 in 0ms:
sed: app/src/Modules/Posts/Application/Assembler/PostViewAssembler.php: No such file or directory

exec
/bin/zsh -lc "sed -n '1,180p' app/config/media.php && sed -n '1,160p' app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php && sed -n '1,120p' app/src/Shared/Infrastructure/Configuration/Locale/LocaleConfig.php && sed -n '1,120p' app/src/Shared/Infrastructure/Configuration/User/UserConfig.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

/**
 * Инфраструктурные дефолты модуля Media.
 *
 * Только технические параметры пайплайна загрузки: staging-TTL для MediaExpiration при
 * создании, порог и размер части multipart, драйвер обработки изображений. Бизнес-ограничения
 * загрузки и срок presigned-ссылок сюда не зашиваются — их задаёт потребитель (срок presigned —
 * через MediaUploadSpec для загрузки и FindMediaUrlQuery для скачивания).
 */
return [
    // Срок жизни оригинала в staging-бакете до подтверждения (MediaExpiration при create), секунды.
    'stagingTtlSeconds' => \max(1, (int) \env('MEDIA_STAGING_TTL_SECONDS', 86_400)),

    // Порог: файл размером >= порога загружается через multipart, иначе одиночным PUT.
    'multipartThresholdBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_THRESHOLD_BYTES', 16_777_216)),

    // Размер одной части multipart, байты. S3 требует >= 5 MiB на часть (кроме последней).
    'multipartPartSizeBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_PART_SIZE_BYTES', 8_388_608)),

    // Драйвер обработки изображений Intervention Image: "imagick" (основной) или "gd" (фолбэк).
    'imageProcessingDriver' => (string) \env('MEDIA_IMAGE_PROCESSING_DRIVER', 'imagick'),

    // Путь к бинарю ffmpeg для транскодирования видео и аудио.
    'ffmpegBinaryPath' => (string) \env('MEDIA_FFMPEG_BINARY', '/usr/bin/ffmpeg'),

    // Путь к бинарю ffprobe для чтения метаданных медиа.
    'ffprobeBinaryPath' => (string) \env('MEDIA_FFPROBE_BINARY', '/usr/bin/ffprobe'),

    // Таймаут одного вызова ffmpeg (транскод/probe), секунды.
    'ffmpegTimeoutSeconds' => \max(1, (int) \env('MEDIA_FFMPEG_TIMEOUT_SECONDS', 1800)),

    // Число потоков ffmpeg: 0 = выбирает сам ffmpeg по числу ядер.
    'ffmpegThreads' => \max(0, (int) \env('MEDIA_FFMPEG_THREADS', 0)),
];
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Media;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class MediaConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'media';
    }

    public function __construct(
        public int $stagingTtlSeconds,
        public int $multipartThresholdBytes,
        public int $multipartPartSizeBytes,
        public string $imageProcessingDriver,
        public string $ffmpegBinaryPath,
        public string $ffprobeBinaryPath,
        public int $ffmpegTimeoutSeconds,
        public int $ffmpegThreads,
    ) {
        // Порог multipart должен быть не меньше размера части: иначе файл чуть больше порога,
        // но меньше одной части ушёл бы в multipart с единственной частью — это бессмысленно
        // относительно одиночного PUT и потенциально нарушает лимит S3 ≥ 5 MiB на часть.
        if ($multipartThresholdBytes < $multipartPartSizeBytes) {
            throw new InvalidConfigValueException(
                path: 'media.multipartThresholdBytes',
                expected: \sprintf('>= multipartPartSizeBytes (%d)', $multipartPartSizeBytes),
                actual: (string) $multipartThresholdBytes,
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Locale;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class LocaleConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'locale';
    }

    /**
     * @param list<string> $supported
     */
    public function __construct(
        public array $supported,
        public string $default,
    ) {
        // Базовая локаль обязана входить в белый список: иначе резолв при отсутствии совпадений
        // выставил бы translator в неподдерживаемую локаль без каталога переводов.
        if (!\in_array(needle: $default, haystack: $supported, strict: true)) {
            throw new InvalidConfigValueException(
                path: 'locale.default',
                expected: \sprintf('одна из поддерживаемых (%s)', \implode(separator: ', ', array: $supported)),
                actual: $default,
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\User;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class UserConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'user';
    }

    public function __construct(
        public string $defaultAvatarUrl,
    ) {
        // Дефолтный URL аватара обязан быть непустым: публичный профиль подставляет его, когда у
        // пользователя нет аватара, а NotificationActor::of() бросает на пустой avatarUrl.
        if (\trim($defaultAvatarUrl) === '') {
            throw new InvalidConfigValueException(
                path: 'user.defaultAvatarUrl',
                expected: 'непустой URL аватара по умолчанию',
                actual: $defaultAvatarUrl,
            );
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,160p' app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Middleware;

use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Http\Header\AcceptHeader;
use Spiral\Http\Header\AcceptHeaderItem;
use Spiral\Translator\Translator;

/**
 * Определяет локаль запроса из заголовка Accept-Language, пересечённого с белым списком
 * поддерживаемых локалей, и выставляет её в translator на время обработки запроса.
 *
 * Зависит от конкретного Spiral\Translator\Translator (не TranslatorInterface): метод
 * setLocale() объявлен в LocaleAwareInterface и реализован конкретным Translator, а не
 * в TranslatorInterface. Translator — singleton, поэтому выставленная локаль видна и
 * интерсептору ошибок, который читает тот же инстанс через TranslatorInterface.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Translator $translator,
        private LocaleConfig $localeConfig,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        $matchedLocale = $this->matchSupportedLocale($acceptLanguage);
        $resolvedLocale = $matchedLocale ?? $this->localeConfig->default;

        $this->translator->setLocale($resolvedLocale);
        $this->logger->debug(message: 'Локаль запроса определена.', context: [
            'acceptLanguage' => $acceptLanguage,
            'resolvedLocale' => $resolvedLocale,
            'usedFallback' => $matchedLocale === null,
        ]);

        return $handler->handle($request);
    }

    private function matchSupportedLocale(string $acceptLanguage): string|null
    {
        // getAll() уже отсортирован по quality по убыванию, поэтому first() даёт самый приоритетный язык.
        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
            ->first(fn(string $primarySubtag): bool => \in_array(
                needle: $primarySubtag,
                haystack: $this->localeConfig->supported,
                strict: true,
            ));
    }

    private function primarySubtag(string $value): string
    {
        $lower = \strtolower($value);
        $separatorPosition = \strpos(haystack: $lower, needle: '-');

        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
    }
}

codex
Путь `PostViewAssembler` в плане указан неточно, ищу фактический файл и проверяю все вызовы `getUrls`, чтобы не пропустить смежный код.
exec
/bin/zsh -lc 'rg --files app/src/Modules/Posts | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostCommand.php
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentCommand.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostCommand.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentCommand.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostCommand.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostCommand.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentCommand.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentCommand.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostCommand.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php
app/src/Modules/Posts/Application/Notification/CommentNotificationTarget.php
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php
app/src/Modules/Posts/Application/Notification/PostNotificationType.php
app/src/Modules/Posts/Application/Notification/PostNotifier.php
app/src/Modules/Posts/Application/Post/CommentComposer.php
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php
app/src/Modules/Posts/Application/Post/PostContentComposer.php
app/src/Modules/Posts/Application/Post/PostVisibilityPolicy.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesQuery.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesResult.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostQuery.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsQuery.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedQuery.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php
app/src/Modules/Posts/Application/View/AuthorView.php
app/src/Modules/Posts/Application/View/CommentView.php
app/src/Modules/Posts/Application/View/CommentViewAssembler.php
app/src/Modules/Posts/Application/View/CommentViewCollection.php
app/src/Modules/Posts/Application/View/PostMediaItemView.php
app/src/Modules/Posts/Application/View/PostView.php
app/src/Modules/Posts/Application/View/PostViewAssembler.php
app/src/Modules/Posts/Application/View/PostViewCollection.php
app/src/Modules/Posts/Application/View/TagView.php
app/src/Modules/Posts/Domain/Collection/CommentCollection.php
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php
app/src/Modules/Posts/Domain/Collection/PostCollection.php
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php
app/src/Modules/Posts/Domain/Entity/Comment.php
app/src/Modules/Posts/Domain/Entity/CommentLike.php
app/src/Modules/Posts/Domain/Entity/CommentMention.php
app/src/Modules/Posts/Domain/Entity/Post.php
app/src/Modules/Posts/Domain/Entity/PostBlock.php
app/src/Modules/Posts/Domain/Entity/PostLike.php
app/src/Modules/Posts/Domain/Entity/PostMedia.php
app/src/Modules/Posts/Domain/Entity/PostMention.php
app/src/Modules/Posts/Domain/Entity/PostTag.php
app/src/Modules/Posts/Domain/Enum/AttachmentType.php
app/src/Modules/Posts/Domain/Enum/PostStatus.php
app/src/Modules/Posts/Domain/ValueObject/BlockReason.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedAt.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedBy.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedReason.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedAt.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedBy.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletionReason.php
app/src/Modules/Posts/Domain/ValueObject/CommentId.php
app/src/Modules/Posts/Domain/ValueObject/CommentLikeId.php
app/src/Modules/Posts/Domain/ValueObject/CommentMentionId.php
app/src/Modules/Posts/Domain/ValueObject/CommentParent.php
app/src/Modules/Posts/Domain/ValueObject/CommentText.php
app/src/Modules/Posts/Domain/ValueObject/CommentsCount.php
app/src/Modules/Posts/Domain/ValueObject/LikesCount.php
app/src/Modules/Posts/Domain/ValueObject/MediaPosition.php
app/src/Modules/Posts/Domain/ValueObject/PostBlockId.php
app/src/Modules/Posts/Domain/ValueObject/PostDeletion.php
app/src/Modules/Posts/Domain/ValueObject/PostId.php
app/src/Modules/Posts/Domain/ValueObject/PostLesson.php
app/src/Modules/Posts/Domain/ValueObject/PostLikeId.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaId.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaReference.php
app/src/Modules/Posts/Domain/ValueObject/PostMentionId.php
app/src/Modules/Posts/Domain/ValueObject/PostOriginal.php
app/src/Modules/Posts/Domain/ValueObject/PostPractice.php
app/src/Modules/Posts/Domain/ValueObject/PostTagId.php
app/src/Modules/Posts/Domain/ValueObject/PostText.php
app/src/Modules/Posts/Domain/ValueObject/RepliesCount.php
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletionReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentParentTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostDeletionTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostLessonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostOriginalTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostPracticeTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php
app/src/Modules/Posts/Presentation/Http/Filter/CommentPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeleteCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeletePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetCommentRepliesFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostCommentsFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetUserFeedFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/PublishPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/ReplyCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Resource/AuthorResource.php
app/src/Modules/Posts/Presentation/Http/Resource/CommentResource.php
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php
app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php
app/src/Modules/Posts/Repository/CommentLikeRepository.php
app/src/Modules/Posts/Repository/CommentMentionRepository.php
app/src/Modules/Posts/Repository/CommentRepository.php
app/src/Modules/Posts/Repository/PostBlockRepository.php
app/src/Modules/Posts/Repository/PostLikeRepository.php
app/src/Modules/Posts/Repository/PostMediaRepository.php
app/src/Modules/Posts/Repository/PostMentionRepository.php
app/src/Modules/Posts/Repository/PostRepository.php
app/src/Modules/Posts/Repository/PostTagRepository.php

exec
/bin/zsh -lc "rg -n \"class PostViewAssembler|POST_MEDIA_URL_TTL_SECONDS|getUrls\\(|FindMediaUrlQuery\\(|MediaUrlService\\(|MediaUrlResolverFactory\" app/src tests" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:12:use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:76:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:118:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:156:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:178:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:200:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:213:            mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:20: * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:38:        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:9:use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:93:                mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
app/src/Modules/Media/Application/Service/MediaUrlService.php:34:    public function __construct(private MediaUrlResolverFactory $resolverFactory) {}
app/src/Modules/Media/Application/Service/MediaUrlService.php:36:    public function getUrls(Media $media, int $presignedTtlSeconds): MediaUrlsResult|null
app/src/Modules/Media/Application/Service/MediaUrlResolver.php:14: * через MediaUrlResolverFactory, поэтому вызывающему остаётся передать только пару (storage, path) —
app/src/Modules/Media/Application/Service/MediaUrlResolverFactory.php:17:final readonly class MediaUrlResolverFactory
app/src/Modules/Media/README.md:59:  `MediaUrlResolverFactory` (public — прямой URL, private — presigned), один резолвер на запрос.
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:52:            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
app/src/Modules/Posts/Application/View/PostViewAssembler.php:43:final readonly class PostViewAssembler
app/src/Modules/Posts/Application/View/PostViewAssembler.php:46:    private const int POST_MEDIA_URL_TTL_SECONDS = 3600;
app/src/Modules/Posts/Application/View/PostViewAssembler.php:278:        $mediaUrls = $this->mediaUrlService->getUrls(
app/src/Modules/Posts/Application/View/PostViewAssembler.php:280:            presignedTtlSeconds: self::POST_MEDIA_URL_TTL_SECONDS,

exec
/bin/zsh -lc "rg -n \"new MediaConfig\\(|new LocaleConfig\\(|new UserConfig\\(|localeConfig:|mediaConfig:|userConfig:\" tests app/src" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:41:        new UserConfig(defaultAvatarUrl: '   ');
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:357:        return new MediaConfig(
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:67:        return new ImagickMediaImageProcessor(new MediaConfig(
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:62:        new MediaConfig(
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php:60:        new LocaleConfig(supported: ['ru', 'en'], default: 'de');
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:60:        return new MediaConfig(
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:471:            mediaConfig: $this->mediaConfig(),
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:479:            mediaConfig: $this->mediaConfig(),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:224:            mediaConfig: new MediaConfig(
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:95:            userConfig: $this->getContainer()->get(UserConfig::class),
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:120:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:77:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:27:            localeConfig: new LocaleConfig(supported: ['ru', 'en'], default: 'ru'),
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:55:            localeConfig: $this->getContainer()->get(LocaleConfig::class),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:57:            localeConfig: $this->getContainer()->get(\App\Shared\Infrastructure\Configuration\Locale\LocaleConfig::class),

exec
/bin/zsh -lc "rg -n \"MEDIA_|presignedTtl|TTL|MediaUrlResolverFactory|FindMediaUrl\\(\" .env.sample app/src/Modules/Media/README.md app/config tests" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/README.md:26:| `FindMediaUrl(mediaId, presignedTtlSeconds)` | Query | `MediaUrlsResult` или `null` (полный набор: `original` — оригинал, `null` если он удалён в `readyOriginalRemoved`; `conversions` — все конверсии, каждая со своим типом; вызывающий выбирает нужное по типу, не зная заранее, какие конверсии есть. Не бросает: медиа нет или не финализировано → `null` для best-effort показа. Для public — прямые URL без срока (`presignedTtlSeconds` игнорируется и не валидируется), для private — presigned со сроком из запроса) |
app/src/Modules/Media/README.md:39:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/README.md:40:  — политика загрузки от потребителя. Конверсий здесь нет. `presignedTtl` — срок жизни
app/src/Modules/Media/README.md:59:  `MediaUrlResolverFactory` (public — прямой URL, private — presigned), один резолвер на запрос.
app/src/Modules/Media/README.md:74:   (media-public, anonymous read); private -> presignGet (TTL из presignedTtlSeconds запроса).
app/src/Modules/Media/README.md:170:- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Configuration/Media`): staging-TTL,
app/src/Modules/Media/README.md:173:  env `MEDIA_FFMPEG_BINARY`/`MEDIA_FFPROBE_BINARY`/`MEDIA_FFMPEG_TIMEOUT_SECONDS`/`MEDIA_FFMPEG_THREADS`).
app/src/Modules/Media/README.md:174:  TTL presigned-ссылок в конфиге **нет** — его
app/src/Modules/Media/README.md:175:  задаёт потребитель под контекст: `MediaUploadSpec.presignedTtl` (загрузка) и
app/src/Modules/Media/README.md:176:  `FindMediaUrlQuery.presignedTtlSeconds` (скачивание). Ключи `MEDIA_*` — в `.env.sample` и
app/src/Modules/Media/README.md:214:- Длинные видео ограничены `MediaUploadSpec.maxSize` (потребитель) и `MEDIA_FFMPEG_TIMEOUT_SECONDS`;
.env.sample:69:MEDIA_UPLOAD_STORAGE_SERVER=s3
.env.sample:70:MEDIA_UPLOAD_STORAGE_BUCKET=media-upload
.env.sample:71:MEDIA_UPLOAD_STORAGE_PREFIX=
.env.sample:72:MEDIA_PRIVATE_STORAGE_SERVER=s3
.env.sample:73:MEDIA_PRIVATE_STORAGE_BUCKET=media-private
.env.sample:74:MEDIA_PRIVATE_STORAGE_PREFIX=
.env.sample:75:MEDIA_PUBLIC_STORAGE_SERVER=s3
.env.sample:76:MEDIA_PUBLIC_STORAGE_BUCKET=media-public
.env.sample:77:MEDIA_PUBLIC_STORAGE_PREFIX=
.env.sample:80:MEDIA_STAGING_TTL_SECONDS=86400
.env.sample:81:MEDIA_MULTIPART_THRESHOLD_BYTES=16777216
.env.sample:82:MEDIA_MULTIPART_PART_SIZE_BYTES=8388608
.env.sample:83:MEDIA_IMAGE_PROCESSING_DRIVER=imagick
.env.sample:85:MEDIA_FFMPEG_BINARY=/usr/bin/ffmpeg
.env.sample:86:MEDIA_FFPROBE_BINARY=/usr/bin/ffprobe
.env.sample:87:MEDIA_FFMPEG_TIMEOUT_SECONDS=1800
.env.sample:88:MEDIA_FFMPEG_THREADS=0
app/config/media.php:8: * Только технические параметры пайплайна загрузки: staging-TTL для MediaExpiration при
app/config/media.php:15:    'stagingTtlSeconds' => \max(1, (int) \env('MEDIA_STAGING_TTL_SECONDS', 86_400)),
app/config/media.php:18:    'multipartThresholdBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_THRESHOLD_BYTES', 16_777_216)),
app/config/media.php:21:    'multipartPartSizeBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_PART_SIZE_BYTES', 8_388_608)),
app/config/media.php:24:    'imageProcessingDriver' => (string) \env('MEDIA_IMAGE_PROCESSING_DRIVER', 'imagick'),
app/config/media.php:27:    'ffmpegBinaryPath' => (string) \env('MEDIA_FFMPEG_BINARY', '/usr/bin/ffmpeg'),
app/config/media.php:30:    'ffprobeBinaryPath' => (string) \env('MEDIA_FFPROBE_BINARY', '/usr/bin/ffprobe'),
app/config/media.php:33:    'ffmpegTimeoutSeconds' => \max(1, (int) \env('MEDIA_FFMPEG_TIMEOUT_SECONDS', 1800)),
app/config/media.php:36:    'ffmpegThreads' => \max(0, (int) \env('MEDIA_FFMPEG_THREADS', 0)),
app/config/storage.php:85:            'server' => \env('MEDIA_UPLOAD_STORAGE_SERVER', 's3'),
app/config/storage.php:86:            'bucket' => \env('MEDIA_UPLOAD_STORAGE_BUCKET', 'media-upload'),
app/config/storage.php:87:            'prefix' => \env('MEDIA_UPLOAD_STORAGE_PREFIX', null),
app/config/storage.php:91:            'server' => \env('MEDIA_PRIVATE_STORAGE_SERVER', 's3'),
app/config/storage.php:92:            'bucket' => \env('MEDIA_PRIVATE_STORAGE_BUCKET', 'media-private'),
app/config/storage.php:93:            'prefix' => \env('MEDIA_PRIVATE_STORAGE_PREFIX', null),
app/config/storage.php:97:            'server' => \env('MEDIA_PUBLIC_STORAGE_SERVER', 's3'),
app/config/storage.php:98:            'bucket' => \env('MEDIA_PUBLIC_STORAGE_BUCKET', 'media-public'),
app/config/storage.php:99:            'prefix' => \env('MEDIA_PUBLIC_STORAGE_PREFIX', null),
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:11: * постоянный -> ERROR) и контекста (например, applied presignedTtlSeconds в запросе загрузки).
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:12:use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:76:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:118:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:156:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:178:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:190:        // невалидный TTL (вне диапазона) проходит как успех без presignGet.
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:200:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:213:            mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:57:            spec: $this->spec(presignedTtl: 300),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:65:        // TTL presigned-ссылки берётся из спеки (300), а не из конфиг-дефолта 900.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:72:        self::assertSame(300, $logger->contextFor('Запрошена загрузка медиа.')['presignedTtlSeconds']);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:101:            spec: $this->spec(maxSize: 5_242_880, presignedTtl: 600),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:111:        // TTL частей multipart тоже берётся из спеки (600), а не из конфига.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:241:        int $presignedTtl = 300,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:248:            presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:9:use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:93:                mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
tests/Unit/Shared/Infrastructure/Framework/Middleware/RecordingCache.php:12: * Управляемый временем PSR-16 кэш для тестов rate limit: TTL истекает по общим часам MockClock,
tests/Unit/Shared/Infrastructure/Framework/Middleware/RecordingCache.php:13: * а TTL счётчика (ключи без суффикса `:reset`) записываются для проверки фиксированного окна.
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:72:        // Фиксированное окно: TTL счётчика уменьшается к концу окна (60, затем 40, затем 20),

exec
/bin/zsh -lc "sed -n '1,330p' app/src/Modules/Posts/Application/View/PostViewAssembler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Application\Service\MediaUrlService;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostMediaRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Modules\Posts\Repository\PostTagRepository;
use App\Modules\Tags\Application\Dto\TagTextCollection;
use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает read-model PostView из доменной записи, обогащая её данными смежных модулей: автор —
 * через User, URL медиа — через Media, тексты тегов — через Tags; флаг likedByMe и счётчики — из
 * своего модуля. Для репоста доглубляет исходную запись на один уровень (без её собственного
 * оригинала), если она видна зрителю.
 *
 * В листингах пакетно собираются выборки из БД: авторы, флаги likedByMe, медиа и теги берутся одним
 * запросом на страницу (без N+1 на уровне БД). Разрешение URL каждого вложения остаётся поэлементным
 * (mediaItem -> FindMediaUrl): пакетного контракта разрешения URL в Media сейчас нет, поэтому число
 * вызовов растёт линейно с числом вложений на странице. Это сознательный компромисс, а не «без N+1»
 * на уровне URL медиа.
 */
final readonly class PostViewAssembler
{
    // TTL ссылки на медиа записи для показа (для приватного медиа — срок presigned-ссылки).
    private const int POST_MEDIA_URL_TTL_SECONDS = 3600;

    public function __construct(
        private QueryBusInterface $queryBus,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
        private MediaUrlService $mediaUrlService,
        private GetTagsHandler $getTagsHandler,
        private PostRepository $postRepository,
        private PostMediaRepository $postMediaRepository,
        private PostTagRepository $postTagRepository,
        private PostLikeRepository $postLikeRepository,
    ) {}

    public function fromPost(Post $post, UserId $viewer): PostView
    {
        $tags = $this->postTagRepository->findByPostId($post->id);

        return $this->build(
            post: $post,
            author: $this->authorView($post->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $viewer),
            media: $this->mediaItems($this->postMediaRepository->findByPostId($post->id)),
            tags: $this->tagViews(tags: $tags, texts: $this->tagTexts($tags)),
            original: $this->originalView(post: $post, viewer: $viewer),
        );
    }

    public function fromPosts(PostCollection $posts, UserId $viewer): PostViewCollection
    {
        if ($posts->isEmpty()) {
            return new PostViewCollection();
        }

        $postIds = $this->postIds($posts);
        $authors = $this->authorViews($posts);
        $likedPostIds = $this->likedPostIds(posts: $posts, viewer: $viewer);
        $mediaByPost = $this->mediaCollectionsByPost($postIds);
        $tagsByPost = $this->tagCollectionsByPost($postIds);
        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));
        $originals = $this->originalViewsByPost(posts: $posts, viewer: $viewer);

        return new PostViewCollection(
            $posts->toBase()->map(function (Post $post) use ($authors, $likedPostIds, $mediaByPost, $tagsByPost, $tagTexts, $originals): PostView {
                $postId = $post->id->value();
                $originalId = $post->original->value();

                return $this->build(
                    post: $post,
                    author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
                    likedByMe: isset($likedPostIds[$postId]),
                    media: $this->mediaItems($mediaByPost[$postId] ?? new PostMediaCollection()),
                    tags: $this->tagViews(tags: $tagsByPost[$postId] ?? new PostTagCollection(), texts: $tagTexts),
                    original: $originalId !== null ? ($originals[$originalId] ?? null) : null,
                );
            }),
        );
    }

    /**
     * @param list<PostMediaItemView> $media
     * @param list<TagView> $tags
     */
    private function build(Post $post, AuthorView $author, bool $likedByMe, array $media, array $tags, PostView|null $original): PostView
    {
        return new PostView(
            id: $post->id->value(),
            text: $post->text->value(),
            status: $post->status->value,
            attachmentType: $this->attachmentType(post: $post, media: $media)->value,
            likesCount: $post->likesCount->value(),
            repostsCount: $post->repostsCount->value(),
            commentsCount: $post->commentsCount->value(),
            likedByMe: $likedByMe,
            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
            author: $author,
            media: $media,
            tags: $tags,
            original: $original,
        );
    }

    /**
     * Тип вложения для ответа. Если запись помечена как media, но после мягкой деградации
     * недоступного вложения видимых медиа не осталось, отдаём none — иначе клиент получил бы
     * attachmentType "media" с пустым media и рассогласованный контракт.
     *
     * @param list<PostMediaItemView> $media
     */
    private function attachmentType(Post $post, array $media): AttachmentType
    {
        if ($post->attachmentType === AttachmentType::Media && $media === []) {
            return AttachmentType::None;
        }

        return $post->attachmentType;
    }

    private function authorView(UserId $userId): AuthorView
    {
        $profile = $this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery($userId->value()),
            handler: $this->getUserPublicProfileHandler->handle(...),
        );

        return new AuthorView(userId: $profile->userId, name: $profile->name, avatarUrl: $profile->avatarUrl);
    }

    /**
     * @return array<string, AuthorView>
     */
    private function authorViews(PostCollection $posts): array
    {
        $userIds = \array_values($posts
            ->toBase()
            ->map(static fn(Post $post): string => $post->userId->value())
            ->unique()
            ->all());

        $profiles = $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery($userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );

        $authors = [];

        foreach ($profiles as $profile) {
            $authors[$profile->userId] = new AuthorView(
                userId: $profile->userId,
                name: $profile->name,
                avatarUrl: $profile->avatarUrl,
            );
        }

        return $authors;
    }

    /**
     * Берёт автора из пакетной карты профилей. GetUserPublicProfiles молча опускает отсутствующих,
     * поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
     * (fromPost -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
    }

    /**
     * @return array<string, true>
     */
    private function likedPostIds(PostCollection $posts, UserId $viewer): array
    {
        $postIds = $posts->mapToList(static fn(Post $post): PostId => $post->id);
        $liked = [];

        foreach ($this->postLikeRepository->findByUserAndPostIds($viewer, ...$postIds) as $like) {
            $liked[$like->postId->value()] = true;
        }

        return $liked;
    }

    /**
     * Медиа набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     * Значение — типизированная коллекция вложений, поэтому контракт не содержит вложенных массивов.
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostMediaCollection>
     */
    private function mediaCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postMediaRepository->findByPostIds(...$postIds) as $postMedia) {
            $byPost[$postMedia->postId->value()] ??= new PostMediaCollection();
            $byPost[$postMedia->postId->value()]->push($postMedia);
        }

        return $byPost;
    }

    /**
     * Теги набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostTagCollection>
     */
    private function tagCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postTagRepository->findByPostIds(...$postIds) as $postTag) {
            $byPost[$postTag->postId->value()] ??= new PostTagCollection();
            $byPost[$postTag->postId->value()]->push($postTag);
        }

        return $byPost;
    }

    /**
     * @return list<PostMediaItemView>
     */
    private function mediaItems(PostMediaCollection $media): array
    {
        $items = [];

        foreach ($media as $postMedia) {
            $item = $this->mediaItem($postMedia);

            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Разрешает ссылку на медиа записи из УЖЕ загруженной сущности Media (relation post_media.media
     * грузится eager в PostMediaRepository вместе с конверсиями — без N+1 на вложение). Построение URL
     * делегируется MediaUrlService без обращения в БД. Если медиа не готово или его оригинал удалён,
     * недоступное вложение исключается из ответа, а не роняет чтение ленты в 500. Берётся оригинал;
     * конверсии (постер видео, превью) сейчас в ленте не используются — это отдельная задача.
     */
    private function mediaItem(PostMedia $postMedia): PostMediaItemView|null
    {
        $mediaUrls = $this->mediaUrlService->getUrls(
            media: $postMedia->media,
            presignedTtlSeconds: self::POST_MEDIA_URL_TTL_SECONDS,
        );

        if ($mediaUrls === null || $mediaUrls->original === null) {
            return null;
        }

        return new PostMediaItemView(
            mediaId: $postMedia->mediaId->value(),
            url: $mediaUrls->original->url,
            position: $postMedia->position->value(),
        );
    }

    /**
     * Тексты тегов набора связей одним запросом к Tags. Несколько коллекций (по записи в листинге)
     * собираются в один батч, чтобы не ходить в Tags на каждую запись.
     */
    private function tagTexts(PostTagCollection ...$tagCollections): TagTextCollection
    {
        $tagIds = [];

        foreach ($tagCollections as $tags) {
            foreach ($tags as $postTag) {
                $tagIds[] = $postTag->tagId->value();
            }
        }

        if ($tagIds === []) {
            return new TagTextCollection();
        }

        return $this->queryBus->dispatch(
            query: new GetTagsQuery(\array_values(\array_unique($tagIds))),
            handler: $this->getTagsHandler->handle(...),
        );
    }

    /**
     * Строит представления тегов записи из общего батча текстов. В листинге батч содержит теги всех
     * записей страницы, поэтому теги, не относящиеся к этой записи, отсеиваются по принадлежности.
     * Принадлежность проверяется по заранее построенному множеству id связей записи за O(1) (вместо
     * линейного поиска по связям на каждый элемент батча) — это убирает квадратичность на странице с
     * тег-тяжёлыми записями. Порядок тегов в ответе определяется выборкой текстов из Tags и не
     * является частью контракта (в post_tags нет колонки позиции — порядок добавления не хранится).
     *
     * @return list<TagView>
     */
    private function tagViews(PostTagCollection $tags, TagTextCollection $texts): array
    {
        $postTagIds = [];

exec
/bin/zsh -lc "sed -n '1,280p' tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
use App\Modules\Media\Application\Service\MediaUrlService;
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
use App\Shared\Domain\ValueObject\UserId;

final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsNullForMissingMedia(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullForMediaThatIsNotFinalized(): void
    {
        // Ещё не ready и не readyOriginalRemoved -> best-effort null, чтобы вызывающий подставил дефолт.
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);
        $this->cleanOrmHeap();

        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsOriginalAndAllConversionsForReadyPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $video = $this->videoConversion($media);
        $audio = $this->audioConversion($media);
        $this->persist($media, $thumbnail, $video, $audio);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — прямой публичный URL без срока.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->original->url);
        self::assertNull($result->original->expiresAt);

        // Все три конверсии присутствуют, каждая со своим видом, типом и URL по своему пути.
        self::assertCount(3, $result->conversions);

        $thumbnailUrl = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame(MediaType::Image, $thumbnailUrl->kind);
        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailUrl->url);
        self::assertNull($thumbnailUrl->expiresAt);

        $videoUrl = $this->conversionByType($result->conversions, MediaVideoConversionType::NormalizedMp4H264);
        self::assertSame(MediaType::Video, $videoUrl->kind);
        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoUrl->url);

        $audioUrl = $this->conversionByType($result->conversions, MediaAudioConversionType::NormalizedAacM4a);
        self::assertSame(MediaType::Audio, $audioUrl->kind);
        self::assertSame('http://minio/media-public/' . $audio->path->value(), $audioUrl->url);
    }

    public function testReturnsPresignedOriginalAndConversionForReadyPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $thumbnail = $this->thumbnailConversion($media);
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('publicUrl');
        $fileService->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — presigned-ссылка со сроком.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/signed/' . $media->path->value(), $result->original->url);
        self::assertNotNull($result->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $result->original->expiresAt->getTimestamp(),
            5,
        );

        // Конверсия private тоже presigned со сроком.
        self::assertCount(1, $result->conversions);
        $conversion = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame('http://minio/signed/' . $thumbnail->path->value(), $conversion->url);
        self::assertNotNull($conversion->expiresAt);
    }

    public function testOmitsOriginalButKeepsConversionsForReadyOriginalRemovedMedia(): void
    {
        // Ключевое поведение: после удаления оригинала результат не null — original = null,
        // а конверсии продолжают резолвиться. Никакого 404.
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $media->markReadyOriginalRemoved();
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNull($result->original);
        self::assertCount(1, $result->conversions);
        self::assertSame(
            'http://minio/media-public/' . $thumbnail->path->value(),
            $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail)->url,
        );
    }

    public function testReturnsEmptyConversionsForReadyMediaWithoutConversions(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertTrue($result->conversions->isEmpty());
    }

    public function testIgnoresInvalidTtlForPublicMedia(): void
    {
        // Для public-медиа срок не применяется и MediaPresignedTtl не строится, поэтому заведомо
        // невалидный TTL (вне диапазона) проходит как успех без presignGet.
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertNull($result->original->expiresAt);
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
    {
        return new FindMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
        );
    }

    private function conversionByType(
        MediaConversionUrlCollection $conversions,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaConversionUrl {
        $conversion = $conversions->ofType($type);
        self::assertNotNull($conversion, \sprintf('Конверсия типа %s не найдена в результате.', $type->value));

        return $conversion;
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

exec
/bin/zsh -lc "sed -n '1,300p' tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
use App\Modules\Media\Domain\Enum\MediaType;
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

    public function testRequestsDocumentUpload(): void
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignPut')->willReturn('http://minio/put-url');

        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
            fileMeta: $this->fileMeta(fileName: 'document.pdf', mimeType: 'application/pdf'),
        ));

        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
        self::assertNotNull($media);
        self::assertSame(MediaType::Document, $media->type);
        self::assertSame(MediaStatus::WaitingUpload, $media->status);
        // Документ кладётся во временное хранилище как и другие типы: путь начинается с uploads/.
        self::assertStringStartsWith('uploads/', $media->path->value());
    }

    public function testRequestsDocumentUploadWithCharsetMime(): void
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignPut')->willReturn('http://minio/put-url');

        // Спецификация задаёт базовый MIME без параметра, файл приходит с ;charset=utf-8 —
        // нормализация в containsMimeType должна пропустить загрузку.
        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')])),
            fileMeta: $this->fileMeta(fileName: 'note.md', mimeType: 'text/markdown;charset=utf-8'),
        ));

        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
        self::assertNotNull($media);
        self::assertSame(MediaType::Document, $media->type);
    }

    public function testRejectsDocumentMimeOutsideSpec(): void
    {
        // Резолвер поддерживает text/csv как документ, но спецификация его не разрешает —
        // потребитель сужает набор, а не расширяет.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.mime_not_allowed');

        $this->handler()->handle(new RequestMediaUploadCommand(
            userId: UserId::generate()->value(),
            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
            fileMeta: $this->fileMeta(fileName: 'sheet.csv', mimeType: 'text/csv'),
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

    private function spec(
        int $maxSize = 1_048_576,
        int $presignedTtl = 300,
        MediaMimeTypeCollection|null $allowedMimeTypes = null,
    ): MediaUploadSpec {
        return new MediaUploadSpec(
            allowedMimeTypes: $allowedMimeTypes ?? new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
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
/bin/zsh -lc "sed -n '1,180p' composer.json && sed -n '1,220p' phpunit.xml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
{
    "name": "spiral/app",
    "type": "project",
    "license": "MIT",
    "description": "Spiral Application installer",
    "homepage": "https://spiral.dev",
    "support": {
        "issues": "https://github.com/spiral/app/issues",
        "source": "https://github.com/spiral/app"
    },
    "require": {
        "php": ">=8.5 <8.6",
        "ext-intl": "*",
        "ext-mbstring": "*",
        "ext-pdo_pgsql": "*",
        "ext-redis": "*",
        "ext-sockets": "*",
        "aws/aws-sdk-php": "^3.384",
        "cuyz/valinor": "^2.4",
        "gian-tiaga/spiral-api-errors": "dev-main",
        "gian-tiaga/spiral-cqrs": "dev-main",
        "gian-tiaga/spiral-openapi": "dev-main",
        "illuminate/collections": "^13.0",
        "illuminate/support": "^13.0",
        "intervention/image": "^4",
        "kreait/firebase-php": "^8.2",
        "league/flysystem-aws-s3-v3": "^3.34",
        "php-ffmpeg/php-ffmpeg": "^1.4",
        "psr/clock": "^1.0",
        "spiral-packages/league-event": "^1.0.1",
        "spiral-packages/scheduler": "^2.1",
        "spiral-packages/symfony-validator": "^1.5",
        "spiral/cycle-bridge": "^2.11",
        "spiral/data-grid-bridge": "^3.0.1",
        "spiral/framework": "^3.15.7",
        "spiral/http": "^3.15",
        "spiral/nyholm-bridge": "^1.3",
        "spiral/roadrunner-bridge": "^4.0",
        "spiral/roadrunner-cli": "^2.5",
        "spiral/sentry-bridge": "^2.3",
        "spiral/temporal-bridge": "^3.3",
        "spiral/translator": "^3.15",
        "spiral/twig-bridge": "^2.0.1",
        "swagger-api/swagger-ui": "^5.32",
        "symfony/clock": "^8.0"
    },
    "require-dev": {
        "brianium/paratest": "7.22.4",
        "friendsofphp/php-cs-fixer": "*",
        "gian-tiaga/phpstan-strict-rules": "*",
        "phpstan/phpstan": "^2.1.54",
        "spiral/code-style": "^2.2",
        "spiral/dumper": "^3.3.1",
        "spiral/testing": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/src"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests"
        }
    },
    "repositories": [
        {
            "type": "path",
            "url": "packages/*",
            "options": {
                "symlink": true
            }
        }
    ],
    "extra": {
        "publish-cmd": "php app.php publish"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "php-http/discovery": true,
            "spiral/composer-publish-plugin": true
        }
    },
    "scripts": {
        "phpstan": "phpstan analyse --debug",
        "cs": "php-cs-fixer fix --dry-run --diff -v",
        "cs:fix": "php-cs-fixer fix -v",
        "test": "phpunit",
        "test:parallel": "vendor/bin/paratest --processes \"${TEST_PARALLEL_PROCESSES:-4}\"",
        "test-coverage": "echo '[coverage] драйвер=PCOV clover=runtime/coverage/clover.xml порог=100%' && mkdir -p runtime/coverage && XDEBUG_MODE=off php -d pcov.enabled=1 -d pcov.directory=app/src vendor/bin/paratest --passthru-php=\"'-d' 'pcov.enabled=1' '-d' 'pcov.directory=app/src'\" --processes \"${TEST_PARALLEL_PROCESSES:-4}\" --testsuite Unit,Kernel,Feature --coverage-clover runtime/coverage/clover.xml && php docker/test/assert-coverage.php runtime/coverage/clover.xml 100",
        "qa": [
            "@cs",
            "@phpstan",
            "@test-coverage"
        ]
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         backupGlobals="false"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         stopOnError="false"
         failOnDeprecation="true"
         failOnPhpunitDeprecation="true"
         stderr="true"
         cacheDirectory="runtime/.phpunit.cache"
         backupStaticProperties="false"
>
    <coverage/>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Kernel">
            <directory suffix="Test.php">tests/Kernel</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">app/src</directory>
        </include>
    </source>
    <php>
        <env name="DB_CONNECTION" value="pgsql" />
        <env name="DB_LOG_QUERY_PARAMETERS" value="true" />
        <!-- DB_DATABASE, S3_BUCKET, S3_TEST_BUCKET задаёт tests/bootstrap.php по TEST_TOKEN. -->
        <env name="DB_HOST" value="postgres" />
        <env name="DB_PORT" value="5432" />
        <env name="DB_USERNAME" value="postgres" />
        <env name="DB_PASSWORD" value="password" />
        <env name="CYCLE_SCHEMA_CACHE" value="true" />
        <env name="QUEUE_CONNECTION" value="sync" />
        <env name="CACHE_STORAGE" value="local" />
        <env name="SESSION_CACHE_STORAGE" value="local" />
        <env name="SCHEDULER_MUTEX_CACHE_STORAGE" value="local" />
        <env name="REDIS_DSN" value="redis://redis:6379/0" />
        <env name="STORAGE_DEFAULT" value="s3-test" />
        <env name="S3_KEY" value="yoga_loka" />
        <env name="S3_SECRET" value="yoga_loka_password" />
        <env name="S3_ENDPOINT" value="http://minio:9000" />
        <env name="MEDIA_UPLOAD_STORAGE_PREFIX" value="test" />
        <env name="MEDIA_PRIVATE_STORAGE_PREFIX" value="test" />
        <env name="MEDIA_PUBLIC_STORAGE_PREFIX" value="test" />
        <env name="MEDIA_STAGING_TTL_SECONDS" value="86400" />
        <env name="MEDIA_MULTIPART_THRESHOLD_BYTES" value="16777216" />
        <env name="MEDIA_MULTIPART_PART_SIZE_BYTES" value="8388608" />
        <env name="MEDIA_IMAGE_PROCESSING_DRIVER" value="imagick" />
        <env name="MEDIA_FFMPEG_BINARY" value="/usr/bin/ffmpeg" />
        <env name="MEDIA_FFPROBE_BINARY" value="/usr/bin/ffprobe" />
        <env name="MEDIA_FFMPEG_TIMEOUT_SECONDS" value="1800" />
        <env name="MEDIA_FFMPEG_THREADS" value="0" />
        <env name="MAILER_DSN" value="smtp://mailpit:1025" />
        <env name="TEMPORAL_ADDRESS" value="temporal:7233" />
        <env name="TEMPORAL_TASK_QUEUE" value="default" />
        <env name="CENTRIFUGO_API_URL" value="http://centrifugo:8000/api" />
        <env name="APP_ENV" value="testing" />
        <env name="TOKENIZER_CACHE_TARGETS" value="true" />
        <env name="TELEMETRY_DRIVER" value="null" />
        <env name="BROADCAST_DRIVER" value="log" />
        <ini name="error_reporting" value="-1"/>
        <ini name="memory_limit" value="-1"/>
    </php>
</phpunit>

codex
План в целом хорошо покрывает явные места. Ищу теперь неочевидные риски: проверки качества, автозагрузка новых файлов, невозможность `protected static` фабрик, и то, совпадает ли стиль новых DTO с текущими правилами проекта.
exec
/bin/zsh -lc 'find app/src/Modules/*/Application/Dto -maxdepth 1 -type f -print | sort | head -100' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Auth/Application/Dto/IssuedTokenPair.php
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaConversionUrl.php
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
app/src/Modules/Notifications/Application/Dto/FcmPushResult.php
app/src/Modules/Notifications/Application/Dto/NotificationActionPayload.php
app/src/Modules/Notifications/Application/Dto/NotificationActorPayload.php
app/src/Modules/Notifications/Application/Dto/NotificationContent.php
app/src/Modules/Notifications/Application/Dto/NotificationPush.php
app/src/Modules/Notifications/Application/Dto/NotificationSettingView.php
app/src/Modules/Notifications/Application/Dto/NotificationSettingViewCollection.php
app/src/Modules/Notifications/Application/Dto/NotificationTypeDefinitionCollection.php
app/src/Modules/Notifications/Application/Dto/RealtimeNotificationPayload.php
app/src/Modules/Tags/Application/Dto/TagTextCollection.php
app/src/Modules/User/Application/Dto/UserAuthView.php
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
app/src/Modules/User/Application/Dto/UserPublicProfileView.php

exec
/bin/zsh -lc "rg -n \"defineSingletons\\(|\\[self::class, '["'^'"']+'\\]|protected static function|private static function|public static function\" app/src tests packages | head -200" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php:130:    public static function complexRootConfigProvider(): iterable
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:115:    public static function simpleRootConfigProvider(): iterable
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:169:    public static function logLevelProvider(): array
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:200:    public static function failureProvider(): array
tests/TestRuntime.php:19:    public static function runtimeDirectory(string $root): string
tests/TestRuntime.php:24:    public static function storageDirectory(string $root): string
tests/TestRuntime.php:35:    public static function directories(string $root): array
tests/TestRuntime.php:46:    private static function suffixed(string $base): string
app/src/Modules/Tags/Domain/Entity/Tag.php:37:    public static function create(TagText $text, UserId $createdBy): self
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:57:    public static function create(
app/src/Modules/Tags/Domain/ValueObject/TagText.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/Entity/Media.php:110:    public static function create(
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:67:    public static function create(
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:75:    public static function create(
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:77:    public static function create(
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:17:    public static function none(): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:22:    public static function fromString(string $value): self
app/src/Modules/User/Domain/Entity/ReservedNickname.php:38:    public static function create(UserNickname $nickname): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:14:    public static function create(MediaMultipartPartNumber $partNumber, MediaMultipartPartETag $eTag): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:19:    public static function fromValues(int $partNumber, string $eTag): self
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:400:    public static function nonEmptyDocumentPlanProvider(): array
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:496:    public static function outOfRangeConversionDimensionProvider(): array
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:394:    public static function integerValueObjectProvider(): iterable
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:308:    public static function awsErrorTransienceProvider(): array
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:427:    private static function s3Exception(string $awsErrorCode): S3Exception
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:60:    public static function driverProvider(): array
app/src/Modules/User/Domain/Entity/UserBan.php:59:    public static function create(
app/src/Modules/User/Domain/Entity/User.php:77:    public static function create(
app/src/Modules/User/Domain/ValueObject/UserNickname.php:15:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php:15:    public static function unassigned(): self
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php:20:    public static function assignedTo(UserId $userId): self
app/src/Modules/User/Domain/ValueObject/UserBio.php:17:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/UserBio.php:22:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:16:    public static function generate(): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:21:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/UserAvatar.php:16:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/UserAvatar.php:21:    public static function pointingTo(string $mediaId): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:17:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/Email.php:17:    public static function fromString(string $value): self
packages/spiral-openapi/src/Bootloader/OpenApiToolsBootloader.php:20:    public function defineSingletons(): array
packages/spiral-openapi/src/Bootloader/OpenApiToolsBootloader.php:22:        return [...parent::defineSingletons(), OpenApiGenerator::class => [self::class, 'openApiGenerator']];
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php:12:    public static function castDatabaseValue(string|null $value): UserAvatar
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php:21:    public static function uncastValue(UserAvatar|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php:12:    public static function castDatabaseValue(string|null $value): UserBio
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php:21:    public static function uncastValue(UserBio|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:12:    public static function castDatabaseValue(string|\DateTimeInterface|null $value): UserDeletion
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:29:    public static function uncastValue(UserDeletion|null $value): \DateTimeImmutable|null
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:41:    public static function documentMimeTypeProvider(): array
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:104:    public static function unsupportedMimeTypeProvider(): array
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php:12:    public static function castDatabaseValue(string|null $value): UserSpiritualName
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php:21:    public static function uncastValue(UserSpiritualName|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php:12:    public static function castDatabaseValue(string|null $value): UserLocation
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php:21:    public static function uncastValue(UserLocation|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php:13:    public static function castDatabaseValue(string|null $value): ReservedNicknameHolder
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php:22:    public static function uncastValue(ReservedNicknameHolder|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php:12:    public static function castDatabaseValue(string|null $value): BanUnbannedReason
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php:21:    public static function uncastValue(BanUnbannedReason|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php:13:    public static function castDatabaseValue(string|null $value): BanUnbannedBy
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php:22:    public static function uncastValue(BanUnbannedBy|null $value): string|null
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php:12:    public static function castDatabaseValue(string|\DateTimeInterface|null $value): BanExpiration
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php:29:    public static function uncastValue(BanExpiration|null $value): \DateTimeImmutable|null
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php:12:    public static function castDatabaseValue(string|\DateTimeInterface|null $value): BanUnbannedAt
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php:29:    public static function uncastValue(BanUnbannedAt|null $value): \DateTimeImmutable|null
tests/Unit/Modules/Notifications/Infrastructure/Cycle/NotificationTypecastTest.php:132:    public static function settingStatusProvider(): iterable
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php:13:    public static function notUnbanned(): self
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php:18:    public static function at(\DateTimeImmutable $unbannedAt): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php:16:    public static function zero(): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:31:    public static function fromPeaks(array $peaks): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:15:    public static function permanent(): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:20:    public static function temporaryUntil(\DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:22:    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:29:    public static function imageConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:45:    public static function videoConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:61:    public static function audioConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:77:    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:91:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:134:    private static function sanitizeExtension(string $extension): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:145:    private static function assertValid(string $value): void
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php:15:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php:20:    public static function by(UserId $userId): self
app/src/Modules/Auth/Presentation/Http/Resource/SessionResource.php:26:    public static function fromSession(AuthSession $session, string $currentSessionId): self
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:17:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php:17:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php:22:    public static function of(string $value): self
app/src/Modules/Auth/Presentation/Http/Resource/VerifyResultResource.php:18:    public static function fromResult(VerifyLoginCodeResult $verifyLoginCodeResult): self
app/src/Modules/Auth/Presentation/Http/Resource/TokenPairResource.php:19:    public static function fromPair(IssuedTokenPair $pair): self
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:13:    public static function active(): self
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:18:    public static function at(\DateTimeImmutable $deletedAt): self
app/src/Modules/User/Domain/ValueObject/UserName.php:17:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/UserName.php:54:    private static function normalize(string $value): string
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:95:    protected static function probeInt(AbstractData $data, string $key): int
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:102:    protected static function probeFloat(AbstractData $data, string $key): float
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:109:    protected static function classifyFailure(\Throwable $exception, string $message): MediaProcessorFailedException
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:122:    private static function isTransientFailure(\Throwable $exception): bool
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:126:    private static function assertSourceIsAudio(FFProbe $probe, string $sourceFile): void
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:147:    private static function isAttachedPicture(Stream $videoStream): bool
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php:17:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php:22:    public static function fromString(string $value): self
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php:64:    private static function normalize(string $value): string
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:74:    public static function emailEquivalenceProvider(): array
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:226:    public static function validIpProvider(): array
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:247:    public static function absentIpProvider(): array
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:306:    public static function absentUserAgentProvider(): array
app/src/Modules/User/Domain/ValueObject/UserLocation.php:17:    public static function none(): self
app/src/Modules/User/Domain/ValueObject/UserLocation.php:22:    public static function fromString(string $value): self
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php:9:    public static function unsupportedDriver(string $driver): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:11:    public static function bucketAliasMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:16:    public static function bucketNameMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:21:    public static function serverMissing(MediaStorage $storage, string $server): self
app/src/Modules/User/Domain/ValueObject/BanReason.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:13:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:45:    public static function uncastValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:12:    public static function castDatabaseValue(string $value): MediaWaveform
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:36:    public static function uncastValue(MediaWaveform $value): string
app/src/Modules/Auth/Domain/Entity/LoginCode.php:50:    public static function issue(
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:30:    public static function uncastValue(
app/src/Modules/User/Domain/ValueObject/BanExpiration.php:13:    public static function permanent(): self
app/src/Modules/User/Domain/ValueObject/BanExpiration.php:18:    public static function until(\DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:22:    public static function uncastValue(
app/src/Modules/Auth/Domain/Entity/AuthToken.php:60:    public static function issue(
app/src/Modules/Auth/Domain/Entity/RegistrationTicket.php:46:    public static function issue(
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:29:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:37:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Auth/Domain/ValueObject/UserAgent.php:15:    public static function fromNullable(string|null $value): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:30:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:38:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php:24:    public static function fromString(string $value): self
app/src/Modules/Auth/Domain/ValueObject/KnownIp.php:18:    public static function fromString(string $value): self
app/src/Modules/Auth/Domain/ValueObject/Ip.php:16:    public static function fromNullable(string|null $value): self
app/src/Modules/Auth/Domain/ValueObject/KnownUserAgent.php:21:    public static function fromString(string $value): self
app/src/Modules/Auth/Domain/ValueObject/SessionDevice.php:19:    public static function fromRequest(string|null $ip, string|null $userAgent): self
app/src/Modules/Auth/Domain/ValueObject/SessionDevice.php:27:    public static function unknown(): self
app/src/Modules/Auth/Domain/ValueObject/CodeAttempts.php:21:    public static function initial(): self
app/src/Modules/Auth/Domain/ValueObject/CodeAttempts.php:26:    public static function fromInt(int $value): self
app/src/Modules/Auth/Domain/ValueObject/Consumption.php:22:    public static function notConsumed(): self
app/src/Modules/Auth/Domain/ValueObject/Consumption.php:27:    public static function at(\DateTimeImmutable $consumedAt): self
app/src/Modules/Auth/Domain/ValueObject/TokenHash.php:20:    public static function fromRawToken(string $rawToken): self
app/src/Modules/Auth/Domain/ValueObject/TokenHash.php:29:    public static function fromString(string $value): self
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationSettingResource.php:23:    public static function fromView(NotificationSettingView $view): self
app/src/Modules/Auth/Domain/ValueObject/SecretHash.php:20:    public static function fromString(string $value): self
app/src/Modules/Auth/Domain/ValueObject/Expiration.php:22:    public static function after(\DateTimeImmutable $now, int $seconds): self
app/src/Modules/Auth/Domain/ValueObject/Expiration.php:27:    public static function fromDateTime(\DateTimeImmutable $value): self
tests/Support/Notifications/FixtureNotificationTypeDefinition.php:23:    public static function withDefaultChannels(
tests/Support/Notifications/FixtureNotificationTypeDefinition.php:33:    public static function allChannels(string $code = 'chat.message_received'): self
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationResource.php:28:    public static function fromEntity(Notification $notification): self
app/src/Modules/Auth/Infrastructure/Cycle/IpTypecast.php:18:    public static function castDatabaseValue(string|null $value): Ip
app/src/Modules/Auth/Infrastructure/Cycle/IpTypecast.php:23:    public static function uncastValue(Ip $value): string|null
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:165:    private static function outboxConfig(
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php:17:    public static function castDatabaseValue(string|\DateTimeInterface|null $value): Consumption
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php:34:    public static function uncastValue(Consumption $value): \DateTimeImmutable|null
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationDeviceTokenResource.php:21:    public static function fromEntity(NotificationDeviceToken $deviceToken): self
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationActionResource.php:21:    public static function fromAction(NotificationAction $action): self
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationActorResource.php:23:    public static function fromActor(NotificationActor $actor): self
app/src/Modules/Auth/Infrastructure/Cycle/ExpirationTypecast.php:16:    public static function castDatabaseValue(string|\DateTimeInterface $value): Expiration
app/src/Modules/Auth/Infrastructure/Cycle/ExpirationTypecast.php:29:    public static function uncastValue(Expiration $value): \DateTimeImmutable
app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php:18:    public static function castDatabaseValue(string|null $value): UserAgent
app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php:23:    public static function uncastValue(UserAgent $value): string|null
app/src/Modules/Notifications/Domain/Entity/Notification.php:73:    public static function create(
app/src/Modules/Notifications/Domain/Entity/NotificationSetting.php:49:    public static function create(
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:107:    public static function fromString(string $value): self
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:136:    public static function fromInt(int $value): self
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:154:    public static function castDatabaseValue(
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:160:    public static function uncastValue(
app/src/Modules/Notifications/Domain/Entity/NotificationDeviceToken.php:43:    public static function create(
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:181:    public static function fromString(string $value): self
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:198:    public static function fromInt(int $value): self
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:211:    public static function fromString(string $value): string
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:231:    public static function castDatabaseValue(mixed $value): object
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:236:    public static function uncastValue(mixed $value): string
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:244:    public static function castDatabaseValue(mixed $value): string
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:249:    public static function uncastValue(mixed $value): string
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:257:    public static function castDatabaseValue(mixed $value): object
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:262:    public static function uncastValue(mixed $value): object
app/src/Modules/Notifications/Domain/ValueObject/NotificationReadState.php:20:    public static function unread(): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationReadState.php:25:    public static function readAt(\DateTimeImmutable $readAt): self
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:42:    public static function acceptLanguageProvider(): iterable
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionType.php:22:    public static function none(): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionType.php:27:    public static function of(string $value): self
tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php:192:    public static function invalidRootConfigProvider(): iterable
app/src/Modules/Notifications/Domain/ValueObject/NotificationTitle.php:21:    public static function fromString(string $value): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationChannelDefaults.php:24:    public static function of(NotificationChannel ...$enabledChannels): self
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:331:    public static function invalidRootConfigProvider(): iterable
app/src/Modules/Auth/Application/Query/GetUserSessions/AuthSession.php:32:    public static function fromTokens(AuthTokenCollection $sessionTokens): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationAction.php:20:    public static function none(): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationAction.php:28:    public static function linkTo(string $actionType, string $actionId): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationAction.php:40:    public static function fromParts(NotificationActionType $actionType, NotificationActionId $actionId): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActor.php:29:    public static function none(): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActor.php:34:    public static function of(UserId $userId, string $name, string $avatarUrl): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActor.php:99:    private static function normalizeName(string $name): string
app/src/Modules/Notifications/Domain/ValueObject/NotificationActor.php:110:    private static function normalizeAvatarUrl(string $avatarUrl): string
app/src/Modules/Notifications/Domain/ValueObject/NotificationTypeCode.php:23:    public static function fromString(string $value): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationBody.php:19:    public static function fromString(string $value): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionId.php:21:    public static function none(): self
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionId.php:26:    public static function of(string $value): self
app/src/Modules/Notifications/Domain/ValueObject/DeviceToken.php:21:    public static function fromString(string $value): self
app/src/Modules/Notifications/Infrastructure/Exception/CentrifugoPresenceException.php:23:    public static function transport(\Throwable $previous): self
app/src/Modules/Notifications/Infrastructure/Exception/CentrifugoPresenceException.php:28:    public static function serverError(int $statusCode): self

exec
/bin/zsh -lc "rg -n \"final readonly class .*Settings|Application\\\\Dto|class .*Settings|new .*Settings\" app/src tests" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Notifications/Presentation/Http/Filter/Setting/UpdateNotificationSettingsFilter.php:16:final class UpdateNotificationSettingsFilter extends AttributesFilter
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:231:            new GetNotificationSettingsQuery(userId: $userId->value()),
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:247:            command: new UpdateNotificationSettingsCommand(userId: $userId->value(), updates: [$update]),
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php:255:            new GetNotificationSettingsQuery(userId: $userId->value()),
app/src/Modules/Notifications/Application/Query/Setting/GetNotificationSettings/GetNotificationSettingsHandler.php:11:final readonly class GetNotificationSettingsHandler
app/src/Modules/Notifications/Application/Query/Setting/GetNotificationSettings/GetNotificationSettingsQuery.php:7:final readonly class GetNotificationSettingsQuery
app/src/Modules/Notifications/Application/Service/NotificationSettingsViewFactory.php:22:final readonly class NotificationSettingsViewFactory
app/src/Modules/Notifications/Presentation/Http/Controller/NotificationSettingController.php:35:            query: new GetNotificationSettingsQuery(userId: $notificationRecipientFilter->authUserId),
app/src/Modules/Notifications/Presentation/Http/Controller/NotificationSettingController.php:52:            command: new UpdateNotificationSettingsCommand(
app/src/Modules/Notifications/Application/Command/Setting/UpdateNotificationSettings/UpdateNotificationSettingsCommand.php:7:final readonly class UpdateNotificationSettingsCommand
app/src/Modules/Notifications/Application/Command/Setting/UpdateNotificationSettings/UpdateNotificationSettingsHandler.php:27:final readonly class UpdateNotificationSettingsHandler

exec
/bin/zsh -lc "sed -n '1,140p' tests/App/TestKernel.php && sed -n '1,120p' tests/Kernel/Modules/Auth/AuthBootloaderTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\App;

use App\Shared\Infrastructure\Framework\Kernel;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Testing\Traits\TestableKernel;
use Tests\App\Bootloader\ApiErrorTestRoutesBootloader;

class TestKernel extends Kernel implements TestableKernelInterface
{
    use TestableKernel;

    #[\Override]
    public function defineBootloaders(): array
    {
        return [
            ...parent::defineBootloaders(),
            ApiErrorTestRoutesBootloader::class,
        ];
    }
}
<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Hash\HmacSecretHasher;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
use Spiral\Auth\TokenStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class AuthBootloaderTest extends TestCase
{
    public function testContractBindingsResolveToImplementations(): void
    {
        self::assertInstanceOf(HmacSecretHasher::class, $this->getContainer()->get(SecretHasherContract::class));
        self::assertInstanceOf(RandomTokenGenerator::class, $this->getContainer()->get(TokenGeneratorContract::class));
        self::assertInstanceOf(CycleTokenStorage::class, $this->getContainer()->get(AuthTokenStorageContract::class));
    }

    public function testCycleTokenStorageRegisteredUnderCycleName(): void
    {
        $storage = $this->getContainer()->get(TokenStorageProviderInterface::class)->getStorage('cycle');

        self::assertInstanceOf(CycleTokenStorage::class, $storage);
    }

    public function testQueueRegistersSendLoginCodeJobWithOutboxSerializer(): void
    {
        $queueConfig = $this->getContainer()->get(ConfiguratorInterface::class)->getConfig('queue');

        self::assertArrayHasKey(SendLoginCodeJob::class, $queueConfig['registry']['handlers']);
        self::assertSame(
            OutboxQueueSerializer::class,
            $queueConfig['registry']['serializers'][SendLoginCodeJob::class],
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' packages/spiral-openapi/src/Bootloader/OpenApiToolsBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\OpenApiGenerator;

final class OpenApiToolsBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [I18nBootloader::class];
    public function init(I18nBootloader $i18n): void
    {
        $i18n->addDirectory(__DIR__ . '/../../locale');
    }
    #[\Override]
    public function defineSingletons(): array
    {
        return [...parent::defineSingletons(), OpenApiGenerator::class => [self::class, 'openApiGenerator']];
    }
    public function openApiGenerator(TranslatorInterface $translator): OpenApiGenerator
    {
        return new OpenApiGenerator(translator: $translator);
    }
}

exec
/bin/zsh -lc "rg -n \"function domainCore\" vendor/spiral -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Framework/Bootloader/DomainBootloader.php:27:    protected static function domainCore(

exec
/bin/zsh -lc "rg -n \"class Binder|bindSingleton|defineSingletons|invoke|\\[\\"'$|callable" vendor/spiral/framework vendor/spiral/core vendor/spiral/boot -g '"'*.php' | head -120" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
rg: vendor/spiral/core: No such file or directory (os error 2)
rg: vendor/spiral/boot: No such file or directory (os error 2)
vendor/spiral/framework/src/Router/src/Router.php:253:            [
vendor/spiral/framework/src/Router/src/Registry/DefaultPatternRegistry.php:12:    private array $patterns = [
vendor/spiral/framework/src/Router/src/Route.php:19: * Default route provides ability to route request to a given callable handler.
vendor/spiral/framework/src/Router/src/Route.php:42:    /** @var string|callable|RequestHandlerInterface|TargetInterface */
vendor/spiral/framework/src/Router/src/Route.php:49:     * @param string|callable|RequestHandlerInterface|TargetInterface $target Callable route target.
vendor/spiral/framework/src/Router/src/Route.php:54:        string|callable|RequestHandlerInterface|TargetInterface $target,
vendor/spiral/framework/src/Router/src/UriHandler.php:27:    private const URI_FIXERS = [
vendor/spiral/framework/src/Router/src/UriHandler.php:79:     * @param \Closure(non-empty-string): non-empty-string $callable Callable must accept string and return string.
vendor/spiral/framework/src/Router/src/UriHandler.php:81:    public function withPathSegmentEncoder(\Closure $callable): self
vendor/spiral/framework/src/Router/src/UriHandler.php:84:        $uriHandler->pathSegmentEncoder = $callable;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:21: * @property-read null|string|callable|RequestHandlerInterface|TargetInterface $target
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:38:    /** @var null|string|callable|RequestHandlerInterface|TargetInterface */
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:79:    public function callable(array|\Closure $callable): self
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:81:        $this->target = $callable;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:167:                    `namespaced`, `groupControllers`, `callable`, `handler` methods.', $this->name),
vendor/spiral/framework/src/Hmvc/src/Core.php:8: * Simple domain core to invoke controller actions.
vendor/spiral/framework/src/Router/src/CoreHandler.php:131:                                [
vendor/spiral/framework/src/Router/src/CoreHandler.php:136:                    attributes: [
vendor/spiral/framework/src/Views/src/ViewManager.php:27:        $this->loader = $factory->make(LoaderInterface::class, [
vendor/spiral/framework/src/Router/src/Target/Namespaced.php:13: * Provides ability to invoke any controller from given namespace.
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:45:    private static array $processTokens = [
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:332:        $this->namespaces[$namespace] = [
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:394:        $this->functions[$name] = [
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:416:        $this->declarations[\token_name($tokenType)][$name] = [
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:665:        $this->namespaces[''] = [
vendor/spiral/framework/src/Session/src/SessionFactory.php:44:        return $this->factory->make(Session::class, [
vendor/spiral/framework/src/Router/src/Target/Group.php:10: * Provides ability to invoke from a given controller set:
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionArgument.php:35:     * Create Argument reflections based on provided set of tokens (fetched from invoke).
vendor/spiral/framework/src/Session/src/Session.php:143:        $_SESSION = [
vendor/spiral/framework/src/Session/src/Session.php:158:        return [
vendor/spiral/framework/src/Hmvc/src/InterceptorPipeline.php:91:            throw new InterceptorException('Unable to invoke pipeline without last handler.');
vendor/spiral/framework/src/Hmvc/src/AbstractCore.php:40:                ->invoke(static fn(#[Proxy] ResolverInterface $resolver): ResolverInterface => $resolver);
vendor/spiral/framework/src/Hmvc/src/AbstractCore.php:54:        return $this->invoke(null, $controller, $method, $parameters);
vendor/spiral/framework/src/Hmvc/src/AbstractCore.php:62:            ? $this->invoke($target->getObject(), $target->getPath()[0], $reflection, $context->getArguments())
vendor/spiral/framework/src/Hmvc/src/AbstractCore.php:87:    private function invoke(?object $object, string $class, \ReflectionMethod $method, array $arguments): mixed
vendor/spiral/framework/src/Hmvc/src/AbstractCore.php:110:        return $method->invokeArgs($object ?? $this->container->get($class), $args);
vendor/spiral/framework/src/Session/src/Config/SessionConfig.php:17:    protected array $config = [
vendor/spiral/framework/src/Cache/src/Config/CacheConfig.php:14:    protected array $config = [
vendor/spiral/framework/src/Logger/src/ListenerRegistryInterface.php:19:     * @param callable(LogEvent): void $listener
vendor/spiral/framework/src/Logger/src/ListenerRegistryInterface.php:21:    public function addListener(callable $listener): self;
vendor/spiral/framework/src/Logger/src/ListenerRegistryInterface.php:26:     * @param callable(LogEvent): void $listener
vendor/spiral/framework/src/Logger/src/ListenerRegistryInterface.php:28:    public function removeListener(callable $listener): void;
vendor/spiral/framework/src/Logger/src/ListenerRegistryInterface.php:31:     * @return array<callable(LogEvent): void>
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerBootloader.php:38:    protected const BINDINGS = [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerBootloader.php:61:            [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerBootloader.php:64:                'exclude' => [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerBootloader.php:70:                'cache' => [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerBootloader.php:74:                'load' => [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:36:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:40:    protected const SINGLETONS = [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:117:        return $factory->make($classLoader, [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:118:            'memory' => $factory->make(Memory::class, [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:129:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:132:            $this->loadReflections($invoker, $classes->getClasses(...), $loader->loadClasses(...));
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:140:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:143:            $this->loadReflections($invoker, $enums->getEnums(...), $loader->loadEnums(...));
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:151:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:154:            $this->loadReflections($invoker, $interfaces->getInterfaces(...), $loader->loadInterfaces(...));
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:159:     * @param callable(): array<class-string, \ReflectionClass> $reflections
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:160:     * @param callable(TokenizationListenerInterface): bool $loader
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:163:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:164:        callable $reflections,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:165:        callable $loader,
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:188:            $invoker->invoke($listener, $classes);
vendor/spiral/framework/src/Logger/src/ListenerRegistry.php:14:    /** @var array<int, callable(LogEvent): void> */
vendor/spiral/framework/src/Logger/src/ListenerRegistry.php:17:    public function addListener(callable $listener): self
vendor/spiral/framework/src/Logger/src/ListenerRegistry.php:26:    public function removeListener(callable $listener): void
vendor/spiral/framework/src/Logger/src/NullLogger.php:20:        callable $receptor,
vendor/spiral/framework/src/Tokenizer/src/Config/TokenizerConfig.php:33:    protected array $config = [
vendor/spiral/framework/src/Tokenizer/src/Config/TokenizerConfig.php:34:        'cache' => [
vendor/spiral/framework/src/Tokenizer/src/Config/TokenizerConfig.php:38:        'load' => [
vendor/spiral/framework/src/Tokenizer/src/Config/TokenizerConfig.php:78:        return [
vendor/spiral/framework/src/Views/src/Bootloader/ViewsBootloader.php:28:    protected const SINGLETONS = [
vendor/spiral/framework/src/Views/src/Bootloader/ViewsBootloader.php:49:            [
vendor/spiral/framework/src/Views/src/Bootloader/ViewsBootloader.php:50:                'cache' => [
vendor/spiral/framework/src/Views/src/Bootloader/ViewsBootloader.php:54:                'namespaces' => [
vendor/spiral/framework/src/Reactor/src/Aggregator/Elements.php:22:        parent::__construct([
vendor/spiral/framework/src/Logger/src/Bootloader/LoggerBootloader.php:22:    protected const SINGLETONS = [
vendor/spiral/framework/src/Views/src/Config/ViewsConfig.php:15:    protected array $config = [
vendor/spiral/framework/src/Views/src/Config/ViewsConfig.php:16:        'cache' => [
vendor/spiral/framework/src/Views/src/Config/ViewsConfig.php:22:        'engines' => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:63:    protected array $bagAssociations = [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:64:        'headers'    => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:69:        'data'       => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:74:        'query'      => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:78:        'cookies'    => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:83:        'files'      => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:88:        'server'     => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:92:        'attributes' => [
vendor/spiral/framework/src/Http/src/Request/InputManager.php:117:    private array $jsonTypes = [
vendor/spiral/framework/src/Http/src/Pipeline.php:102:                attributes: [
vendor/spiral/framework/src/Tokenizer/src/Listener/ListenerInvoker.php:17:    public function invoke(TokenizationListenerInterface $listener, iterable $classes): void
vendor/spiral/framework/src/Http/src/CallableHandler.php:14: * Provides ability to invoke any handler and write it's response into ResponseInterface.
vendor/spiral/framework/src/Http/src/CallableHandler.php:20:    /** @var callable */
vendor/spiral/framework/src/Http/src/CallableHandler.php:21:    private mixed $callable;
vendor/spiral/framework/src/Http/src/CallableHandler.php:24:        callable $callable,
vendor/spiral/framework/src/Http/src/CallableHandler.php:27:        $this->callable = $callable;
vendor/spiral/framework/src/Http/src/CallableHandler.php:42:            $result = \call_user_func($this->callable, $request, $response);
vendor/spiral/framework/src/Streams/src/StreamWrapper.php:29:    private static array $modes = [
vendor/spiral/framework/src/Streams/src/StreamWrapper.php:255:        return [
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedInterfacesLoader.php:19:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedInterfacesLoader.php:22:        parent::__construct($reader, $memory, $invoker, $readCache);
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedClassesLoader.php:17:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedClassesLoader.php:20:        parent::__construct($reader, $memory, $invoker, $readCache);
vendor/spiral/framework/src/Http/src/Http.php:61:    public function setHandler(callable|RequestHandlerInterface $handler): self
vendor/spiral/framework/src/Http/src/Http.php:108:            attributes: [
vendor/spiral/framework/src/Tokenizer/src/Listener/AbstractCachedLoader.php:19:        protected readonly ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Listener/AbstractCachedLoader.php:25:        callable $locator,
vendor/spiral/framework/src/Tokenizer/src/Listener/AbstractCachedLoader.php:26:        callable $reflectionBuilder,
vendor/spiral/framework/src/Tokenizer/src/Listener/AbstractCachedLoader.php:51:        $this->invoker->invoke($listener, \array_map($reflectionBuilder, \array_unique($names)));
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedEnumsLoader.php:19:        ListenerInvoker $invoker,
vendor/spiral/framework/src/Tokenizer/src/Listener/CachedEnumsLoader.php:22:        parent::__construct($reader, $memory, $invoker, $readCache);
vendor/spiral/framework/src/Snapshots/src/Snapshot.php:40:        return [
vendor/spiral/framework/src/Snapshots/src/Snapshot.php:42:            'location' => [
vendor/spiral/framework/src/Http/src/Stream/GeneratorStream.php:136:        $meta = [
vendor/spiral/framework/src/Http/src/Config/HttpConfig.php:14:    protected array $config = [
vendor/spiral/framework/src/Http/src/Config/HttpConfig.php:16:        'headers'    => [
vendor/spiral/framework/src/Exceptions/src/ExceptionHandler.php:35:    protected array $nonReportableExceptions = [
vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:43:        $this->storage[$key] = [
vendor/spiral/framework/src/Exceptions/src/Style/ConsoleStyle.php:15:    protected array $templates = [

codex
Нашёл практический нюанс по бутлоадерам: в кодовой базе публичные фабрики уже используются, а `DomainBootloader::domainCore` действительно `protected static`. То есть выбранный в плане стиль возможен, но его всё равно лучше закрепить отдельным Kernel-тестом для новых биндингов, а не только полагаться на косвенные HTTP-сценарии.
exec
/bin/zsh -lc "sed -n '1,90p' vendor/spiral/framework/src/Framework/Bootloader/DomainBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,220p' vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Spiral\Boot\Bootloader;

/**
 * Provides ability to initiate set of container bindings using simple string form without closures.
 *
 * You can make any initializer automatically bootable by defining boot() method with
 * automatically resolved arguments.
 *
 * Attention, you are able to define your own set of shared (short bindings) components in your
 * bootloader, DO NOT share your business models this way - use regular DI.
 *
 * @phpstan-import-type TConstantBinding from BootloaderInterface
 */
abstract class Bootloader implements BootloaderInterface, DependedInterface
{
    /**
     * @var array<string, class-string|callable>
     * @psalm-var TConstantBinding
     */
    protected const BINDINGS = [];

    /**
     * @var array<string, class-string|callable>
     * @psalm-var TConstantBinding
     */
    protected const SINGLETONS = [];

    /** @var class-string<BootloaderInterface>[]|class-string<DependedInterface>[] */
    protected const DEPENDENCIES = [];

    public function defineBindings(): array
    {
        return static::BINDINGS;
    }

    public function defineSingletons(): array
    {
        return static::SINGLETONS;
    }

    public function defineDependencies(): array
    {
        return static::DEPENDENCIES;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Spiral\Bootloader;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\CompatiblePipelineBuilder;
use Spiral\Core\Core;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

/**
 * Configures global domain core (CoreInterface) with the set of interceptors to alter domain layer functionality.
 *
 * The CoreInterface binding must be complete in child implementation.
 */
abstract class DomainBootloader extends Bootloader
{
    // the set of interceptors for the domain code
    protected const INTERCEPTORS = [];

    protected static function domainCore(
        Core $core,
        ContainerInterface $container,
        ?EventDispatcherInterface $dispatcher = null,
    ): CoreInterface&HandlerInterface {
        $builder = new CompatiblePipelineBuilder($dispatcher);

        $list = [];
        foreach (static::defineInterceptors() as $interceptor) {
            $list[] = $interceptor instanceof CoreInterceptorInterface || $interceptor instanceof InterceptorInterface
                ? $interceptor
                : $container->get($interceptor);
        }

        return $builder
            ->withInterceptors(...$list)
            ->build($core);
    }

    /**
     * Defines list of interceptors.
     */
    protected static function defineInterceptors(): array
    {
        return static::INTERCEPTORS;
    }
}

exec
/bin/zsh -lc "rg -n \"bindSingleton|defineSingletons|BINDINGS|SINGLETONS\" vendor/spiral/framework/src/Boot/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:82:        $container->bindSingleton(ExceptionHandlerInterface::class, $exceptionHandler);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:83:        $container->bindSingleton(ExceptionRendererInterface::class, $exceptionHandler);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:84:        $container->bindSingleton(ExceptionReporterInterface::class, $exceptionHandler);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:85:        $container->bindSingleton(ExceptionHandler::class, $exceptionHandler);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:86:        $container->bindSingleton(KernelInterface::class, $this);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:88:        $container->bindSingleton(self::class, $this);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:89:        $container->bindSingleton(static::class, $this);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:91:        $container->bindSingleton(
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:97:        $container->bindSingleton(FinalizerInterface::class, $this->finalizer);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:143:            $container->bindSingleton(BootloaderRegistryInterface::class, [self::class, 'initBootloaderRegistry']);
vendor/spiral/framework/src/Boot/src/AbstractKernel.php:168:        $this->container->bindSingleton(EnvironmentInterface::class, $environment);
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:24:    protected const BINDINGS = [];
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:30:    protected const SINGLETONS = [];
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:37:        return static::BINDINGS;
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:40:    public function defineSingletons(): array
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:42:        return static::SINGLETONS;
vendor/spiral/framework/src/Boot/src/Attribute/InitMethod.php:25: *         $container->bindSingleton(MyService::class, MyServiceImplementation::class);
vendor/spiral/framework/src/Boot/src/BootloadManager/Initializer.php:139:        foreach ($bootloader->defineSingletons() as $alias => $resolver) {
vendor/spiral/framework/src/Boot/src/BootloadManager/Initializer.php:140:            $this->binder->bindSingleton($alias, $resolver);
vendor/spiral/framework/src/Boot/src/Bootloader/ConfigurationBootloader.php:23:    protected const SINGLETONS = [
vendor/spiral/framework/src/Boot/src/Bootloader/ConfigurationBootloader.php:52:            $this->binder->bindSingleton(ConfigManager::class, $this->createConfigManager());
vendor/spiral/framework/src/Boot/src/Attribute/SingletonMethod.php:44: * This attribute is similar to defining singletons via the `defineSingletons()` method,
vendor/spiral/framework/src/Boot/src/Bootloader/CoreBootloader.php:22:    protected const SINGLETONS = [
vendor/spiral/framework/src/Boot/src/Bootloader/BootloaderInterface.php:47:    public function defineSingletons(): array;

exec
/bin/zsh -lc "sed -n '120,160p' vendor/spiral/framework/src/Boot/src/BootloadManager/Initializer.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    protected function initDefaultChecker(): BootloaderCheckerInterface
    {
        $registry = new CheckerRegistry();
        $registry->register($this->container->get(ConfigChecker::class));
        $registry->register(new ClassExistsChecker());
        $registry->register(new CanBootedChecker($this->bootloaders));

        return new BootloaderChecker($registry);
    }

    /**
     * Resolve all bootloader dependencies and init bindings
     */
    private function initBootloader(BootloaderInterface $bootloader): void
    {
        foreach ($bootloader->defineBindings() as $alias => $resolver) {
            $this->binder->bind($alias, $resolver);
        }

        foreach ($bootloader->defineSingletons() as $alias => $resolver) {
            $this->binder->bindSingleton($alias, $resolver);
        }

        $this->resolveAttributeBindings($bootloader);
    }

    /**
     * Returns merged config. Attribute config has lower priority.
     *
     * @param class-string<BootloaderInterface>|BootloaderInterface $bootloader
     * @throws \ReflectionException
     */
    private function getBootloadConfig(
        string|BootloaderInterface $bootloader,
        array|callable|BootloadConfig $config,
    ): BootloadConfig {
        if ($config instanceof \Closure) {
            $config = $this->container instanceof ResolverInterface
                ? $config(...$this->container->resolveArguments(new \ReflectionFunction($config)))
                : $config();
        }

exec
/bin/zsh -lc "rg -n \"function bindSingleton|class Binder\" vendor/spiral/framework/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Core/src/BinderInterface.php:35:    public function bindSingleton(string $alias, string|array|callable|object $resolver): void;
vendor/spiral/framework/src/Core/src/Internal/Binder.php:15:final class Binder extends StateBinder
vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php:55:    public function bindSingleton(string $alias, mixed $resolver): void
vendor/spiral/framework/src/Core/src/Container.php:255:    public function bindSingleton(string $alias, string|array|callable|object $resolver, ?bool $force = null): void

exec
/bin/zsh -lc "rg -n \"bindSingleton\\(\" vendor/spiral/framework/src/Core/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Core/src/Options.php:34:     * This option will be used only if the `force` argument in {@see Container::bindSingleton()} is null.
vendor/spiral/framework/src/Core/src/BinderInterface.php:35:    public function bindSingleton(string $alias, string|array|callable|object $resolver): void;
vendor/spiral/framework/src/Core/src/Container.php:255:    public function bindSingleton(string $alias, string|array|callable|object $resolver, ?bool $force = null): void
vendor/spiral/framework/src/Core/src/Container.php:261:        $this->binder->bindSingleton($alias, $resolver);
vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php:55:    public function bindSingleton(string $alias, mixed $resolver): void

exec
/bin/zsh -lc "sed -n '1,120p' vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Spiral\Core\Internal\Config;

use Spiral\Core\BinderInterface;
use Spiral\Core\Config\Alias;
use Spiral\Core\Config\Binding;
use Spiral\Core\Config\Factory;
use Spiral\Core\Config\Inflector;
use Spiral\Core\Config\Injectable;
use Spiral\Core\Config\Scalar;
use Spiral\Core\Config\Shared;
use Spiral\Core\Config\DeferredFactory;
use Spiral\Core\Container\Autowire;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Exception\Binder\SingletonOverloadException;
use Spiral\Core\Exception\ConfiguratorException;
use Spiral\Core\Exception\Container\ContainerException;
use Spiral\Core\Internal\State;

/**
 * @psalm-import-type TResolver from BinderInterface
 * @internal
 */
class StateBinder implements BinderInterface
{
    public function __construct(
        protected readonly State $state,
    ) {}

    /**
     * @param TResolver|object $resolver
     */
    public function bind(string $alias, mixed $resolver): void
    {
        if ($resolver instanceof Inflector && (\interface_exists($alias) || \class_exists($alias))) {
            $this->state->inflectors[$alias][] = $resolver;
            return;
        }

        try {
            $config = $this->makeConfig($resolver, false);
        } catch (\Throwable $e) {
            throw $this->invalidBindingException($alias, $e);
        }

        $this->setBinding($alias, $config);
    }

    /**
     * @param TResolver|object $resolver
     */
    public function bindSingleton(string $alias, mixed $resolver): void
    {
        try {
            $config = $this->makeConfig($resolver, true);
        } catch (\Throwable $e) {
            throw $this->invalidBindingException($alias, $e);
        }

        $this->setBinding($alias, $config);
    }

    public function hasInstance(string $alias): bool
    {
        $bindings = &$this->state->bindings;

        $flags = [];
        while ($binding = $bindings[$alias] ?? null and $binding::class === Alias::class) {
            //Checking alias tree
            if ($flags[$binding->alias] ?? false) {
                return $binding->alias === $alias ?: throw new \Exception('Circular alias detected');
            }

            if (\array_key_exists($alias, $this->state->singletons)) {
                return true;
            }

            $flags[$binding->alias] = true;
            $alias = $binding->alias;
        }

        return \array_key_exists($alias, $this->state->singletons) or isset($bindings[$alias]);
    }

    public function removeBinding(string $alias): void
    {
        unset($this->state->bindings[$alias], $this->state->singletons[$alias]);
    }

    public function bindInjector(string $class, string $injector): void
    {
        $this->setBinding($class, new Injectable($injector));
    }

    public function removeInjector(string $class): void
    {
        unset($this->state->injectors[$class]);
        if (!isset($this->state->bindings[$class]) || $this->state->bindings[$class]::class !== Injectable::class) {
            return;
        }
        unset($this->state->bindings[$class]);
    }

    public function hasInjector(string $class): bool
    {
        if (\array_key_exists($class, $this->state->injectors)) {
            return true;
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new ContainerException($e->getMessage(), $e->getCode(), $e);
        }

        if (
            $reflection->implementsInterface(InjectableInterface::class)

exec
/bin/zsh -lc "rg -n \"new \\ReflectionMethod|invokeArgs|is_callable|\\"'$resolver" vendor/spiral/framework/src/Core/src -g '"'*.php' | head -150" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
rg: regex parse error:
    (?:new \ReflectionMethod|invokeArgs|is_callable|$resolver)
           ^^
error: unrecognized escape sequence

exec
/bin/zsh -lc "sed -n '120,260p' vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
            $reflection->implementsInterface(InjectableInterface::class)
            && $reflection->hasConstant('INJECTOR')
        ) {
            $const = $reflection->getConstant('INJECTOR');
            $this->bindInjector($class, $const);

            return true;
        }

        // check interfaces
        foreach ($this->state->injectors as $target => $injector) {
            if (
                (\class_exists($target, true) && $reflection->isSubclassOf($target))
                ||
                (\interface_exists($target, true) && $reflection->implementsInterface($target))
            ) {
                $this->state->bindings[$class] = new Injectable($injector);

                return true;
            }
        }

        return false;
    }

    public function hasBinding(string $alias): bool
    {
        return \array_key_exists($alias, $this->state->bindings);
    }

    private function makeConfig(mixed $resolver, bool $singleton): Binding
    {
        return match (true) {
            $resolver instanceof Binding => $resolver,
            $resolver instanceof \Closure => new Factory($resolver, $singleton),
            $resolver instanceof Autowire => new \Spiral\Core\Config\Autowire($resolver, $singleton),
            $resolver instanceof \WeakReference => new \Spiral\Core\Config\WeakReference($resolver),
            \is_string($resolver) => new Alias($resolver, $singleton),
            \is_scalar($resolver) => new Scalar($resolver),
            \is_object($resolver) => new Shared($resolver, $singleton),
            \is_array($resolver) => $this->makeConfigFromArray($resolver, $singleton),
            default => throw new \InvalidArgumentException('Unknown resolver type.'),
        };
    }

    private function makeConfigFromArray(array $resolver, bool $singleton): Binding
    {
        if (\is_callable($resolver)) {
            return new Factory($resolver, $singleton);
        }

        // Validate lazy invokable array
        if (!isset($resolver[0]) || !isset($resolver[1]) || !\is_string($resolver[1]) || $resolver[1] === '') {
            throw new \InvalidArgumentException('Incompatible array declaration for resolver.');
        }
        if ((!\is_string($resolver[0]) && !\is_object($resolver[0])) || $resolver[0] === '') {
            throw new \InvalidArgumentException('Incompatible array declaration for resolver.');
        }

        return new DeferredFactory($resolver, $singleton);
    }

    private function invalidBindingException(string $alias, \Throwable $previous): \Throwable
    {
        return new ConfiguratorException(
            \sprintf(
                'Invalid binding for `%s`. %s',
                $alias,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }

    private function setBinding(string $alias, Binding $config): void
    {
        if (isset($this->state->singletons[$alias])) {
            throw new SingletonOverloadException($alias);
        }

        $this->state->bindings[$alias] = $config;

        if ($config instanceof Injectable) {
            $this->state->injectors[$alias] = $config->injector;
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,180p' vendor/spiral/framework/src/Core/src/Config/DeferredFactory.php && sed -n '1,140p' vendor/spiral/framework/src/Core/src/Config/Factory.php && rg -n \"DeferredFactory|Config\\\\Factory\" vendor/spiral/framework/src/Core/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 2 in 0ms:
<?php

declare(strict_types=1);

namespace Spiral\Core\Config;

/**
 * Factory that can be resolved later.
 */
final class DeferredFactory extends Binding
{
    /**
     * @param array{0: object|non-empty-string, 1: non-empty-string, ...} $factory
     */
    public function __construct(
        public readonly array $factory,
        public readonly bool $singleton = false,
    ) {}

    public function __toString(): string
    {
        return \sprintf(
            "Deferred factory '%s'->%s()",
            \is_string($this->factory[0]) ? $this->factory[0] : \get_debug_type($this->factory[0]),
            $this->factory[1],
        );
    }
}
<?php

declare(strict_types=1);

namespace Spiral\Core\Config;

use Spiral\Core\Exception\Traits\ClosureRendererTrait;

/**
 * Make a value using a closure.
 */
final class Factory extends Binding
{
    use ClosureRendererTrait;

    public readonly \Closure $factory;

    /** @var class-string|null */
    private readonly ?string $returnClass;

    private readonly int $parametersCount;
    private ?string $definition;

    public function __construct(
        callable $callable,
        public readonly bool $singleton = false,
    ) {
        $this->factory = $callable(...);
        $reflection = new \ReflectionFunction($this->factory);
        $this->parametersCount = $reflection->getNumberOfParameters();

        // Detect the return type of the factory
        $returnType = (string) $reflection->getReturnType();
        $this->returnClass = \class_exists($returnType) ? $returnType : null;

        /** @psalm-suppress TypeDoesNotContainType */
        $this->definition = match (true) {
            \is_string($callable) => $callable,
            \is_array($callable) => \sprintf(
                '%s::%s()',
                \is_object($callable[0]) ? $callable[0]::class : $callable[0],
                $callable[1],
            ),
            \is_object($callable) && $callable::class !== \Closure::class => 'object ' . $callable::class,
            default => null,
        };
    }

    public function getParametersCount(): int
    {
        return $this->parametersCount;
    }

    /**
     * @return class-string|null
     * @internal
     */
    public function getReturnClass(): ?string
    {
        return $this->returnClass;
    }

    public function __toString(): string
    {
        $this->definition ??= $this->renderClosureSignature(new \ReflectionFunction($this->factory));

        return \sprintf(
            'Factory from %s',
            $this->definition,
        );
    }
}
rg: regex parse error:
    (?:DeferredFactory|Config\Factory)
                             ^^
error: unrecognized escape sequence

codex
Подтвердил: `protected static` в массиве `[self::class, 'method']` проходит как отложенная фабрика, а не как обычный `callable`, так что это не блокер. Теперь добираю последние зависимости по тестам профиля и регистрации, чтобы финальный список был не про уже учтённые пункты, а про новые дырки.
exec
/bin/zsh -lc "sed -n '1,180p' tests/Feature/Modules/User/Application/CreateUserHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Command\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;

final class CreateUserHandlerTest extends DatabaseTestCase
{
    public function testCreatesActiveUserAndReturnsId(): void
    {
        $createUserResult = $this->handler()->handle(new CreateUserCommand(
            email: 'new@example.com',
            name: 'Йога Тест',
            nickname: 'new.nick',
            locale: 'ru',
        ));

        self::assertTrue(AbstractUuidV7Id::isUuidV7($createUserResult->userId));

        $this->cleanOrmHeap();
        $createdUser = $this->userRepository()->findById(UserId::fromString($createUserResult->userId));

        self::assertInstanceOf(User::class, $createdUser);
        self::assertSame(UserStatus::Active, $createdUser->status);
        self::assertSame('new@example.com', $createdUser->email->value());
        self::assertSame('new.nick', $createdUser->nickname->value());
        self::assertSame(Locale::Ru, $createdUser->locale);
    }

    public function testFallsBackToDefaultLocaleForUnsupportedValue(): void
    {
        $createUserResult = $this->handler()->handle(new CreateUserCommand(
            email: 'fallback@example.com',
            name: 'Йога Тест',
            nickname: 'fallback.nick',
            locale: 'fr',
        ));

        $this->cleanOrmHeap();
        $createdUser = $this->userRepository()->findById(UserId::fromString($createUserResult->userId));

        self::assertInstanceOf(User::class, $createdUser);
        self::assertSame(
            Locale::from($this->getContainer()->get(LocaleConfig::class)->default),
            $createdUser->locale,
        );
    }

    public function testRejectsTakenEmail(): void
    {
        $this->persist($this->createUser(email: 'taken@example.com', nickname: 'someone'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.email_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'TAKEN@example.com',
            name: 'Йога Тест',
            nickname: 'free.nick',
            locale: 'ru',
        ));
    }

    public function testRejectsTakenNickname(): void
    {
        $this->persist($this->createUser(email: 'owner@example.com', nickname: 'busy.nick'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'BUSY.NICK',
            locale: 'ru',
        ));
    }

    public function testRejectsReservedNickname(): void
    {
        $this->persist(ReservedNickname::create(UserNickname::fromString('reserved')));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'reserved',
            locale: 'ru',
        ));
    }

    private function handler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
            localeConfig: $this->getContainer()->get(LocaleConfig::class),
        );
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::create(
            name: UserName::fromString('Существующий'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    private function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    private function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,170p' tests/Feature/Modules/User/Application/UserApplicationTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Service\MediaUrlResolverFactory;
use App\Modules\Media\Application\Service\MediaUrlService;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\User\UserConfig;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов публичного профиля: создаёт пользователей и медиа, собирает
 * UserPublicProfileAssembler со стабом файлового сервиса (URL предсказуем, без обращения к S3).
 */
abstract class UserApplicationTestCase extends DatabaseTestCase
{
    protected const string STUBBED_AVATAR_URL = 'https://cdn.example/real-avatar.jpg';

    private int $userCounter = 0;

    protected function persistUser(UserAvatar|null $avatar = null, Locale $locale = Locale::Ru): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('profile.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('profile.user%d', $this->userCounter)),
            locale: $locale,
        );

        if ($avatar !== null && !$avatar->isEmpty()) {
            $user->setAvatar($avatar);
        }

        $this->persist($user);

        return $user;
    }

    protected function persistReadyPublicMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        return $media;
    }

    protected function persistNotReadyMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $this->persist($media);

        return $media;
    }

    protected function profileHandlerAssembler(): UserPublicProfileAssembler
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_AVATAR_URL);

        return new UserPublicProfileAssembler(
            findMediaUrlHandler: new FindMediaUrlHandler(
                mediaRepository: $this->getContainer()->get(MediaRepository::class),
                mediaUrlService: new MediaUrlService(new MediaUrlResolverFactory($fileService)),
            ),
            userConfig: $this->getContainer()->get(UserConfig::class),
        );
    }

    protected function defaultAvatarUrl(): string
    {
        return $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl;
    }

    protected function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function createMedia(MediaVisibility $visibility): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php && sed -n '1,120p' tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php && sed -n '1,90p' tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeCommand;
use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Spiral\Translator\TranslatorInterface;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingLoginCodeMailer;
use Tests\TestCase;

final class SendLoginCodeHandlerTest extends TestCase
{
    public function testSendsRussianEmailWithCode(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '123456', locale: 'ru'));

        self::assertCount(1, $loginCodeMailer->sentEmails);
        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('user@example.com', $sentEmail->email);
        self::assertSame('Код для входа в YogaLoka', $sentEmail->subject);
        self::assertStringContainsString('123456', $sentEmail->body);
    }

    public function testSendsEnglishEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '654321', locale: 'en'));

        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('Your YogaLoka login code', $sentEmail->subject);
        self::assertStringContainsString('654321', $sentEmail->body);
    }

    public function testFallsBackToDefaultLocaleForUnsupportedValue(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '111111', locale: 'fr'));

        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('Код для входа в YogaLoka', $sentEmail->subject);
    }

    private function handler(RecordingLoginCodeMailer $loginCodeMailer): SendLoginCodeHandler
    {
        return new SendLoginCodeHandler(
            loginCodeMailer: $loginCodeMailer,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            localeConfig: $this->getContainer()->get(LocaleConfig::class),
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Presentation;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Queue\Exception\RetryException;
use Spiral\Translator\TranslatorInterface;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingLoginCodeMailer;
use Tests\TestCase;

final class SendLoginCodeJobTest extends TestCase
{
    public function testLoadsMessageAndSendsEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($loginCodeMailer),
            logger: new NullLogger(),
        );

        self::assertCount(1, $loginCodeMailer->sentEmails);
        self::assertStringContainsString('123456', $loginCodeMailer->sentEmails[0]->body);
    }

    public function testRetriesWhenMailerFails(): void
    {
        $failingMailer = $this->createStub(LoginCodeMailerContract::class);
        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));

        $this->expectException(RetryException::class);

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($failingMailer),
            logger: new NullLogger(),
        );
    }

    private function loaderReturning(): OutboxMessageLoaderContract
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new LoginCodeRequested(
            email: 'user@example.com',
            code: '123456',
            locale: 'ru',
        ));

        return $loader;
    }

    private function handler(LoginCodeMailerContract $loginCodeMailer): SendLoginCodeHandler
    {
        return new SendLoginCodeHandler(
            loginCodeMailer: $loginCodeMailer,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            localeConfig: $this->getContainer()->get(LocaleConfig::class),
        );
    }

    private function job(): SendLoginCodeJob
    {
        return $this->getContainer()->get(SendLoginCodeJob::class);
    }

    private function envelope(): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::fromString(Uuid::uuid7()->toString()),
            outboxEventType: OutboxEventType::fromString(LoginCodeRequested::class),
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;
use Tests\Feature\Modules\Auth\Application\Fixture\FakeSecretHasher;

abstract class AuthApplicationTestCase extends DatabaseTestCase
{
    protected function secretHasher(): FakeSecretHasher
    {
        return new FakeSecretHasher();
    }

    protected function tokenStorage(): CycleTokenStorage
    {
        return new CycleTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: new RandomTokenGenerator(),
            entityManager: $this->entityManager(),
        );
    }

    protected function createUserHandler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            entityManager: $this->entityManager(),
            logger: new \Psr\Log\NullLogger(),
            localeConfig: $this->getContainer()->get(\App\Shared\Infrastructure\Configuration\Locale\LocaleConfig::class),
        );
    }

    protected function findUserForAuthHandler(): FindUserForAuthHandler
    {
        return new FindUserForAuthHandler(userRepository: $this->userRepository());
    }

    protected function persistUser(string $email, string $nickname, bool $banned = false): User
    {
        $user = User::create(
            name: UserName::fromString('Тест Пользователь'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
        $user->confirmEmail();

        if ($banned) {
            $user->ban();
        }

        $this->entityManager()->persist($user);
        $this->entityManager()->run();

        return $user;
    }

    protected function persistLoginCode(
        string $email,
        string $code,
        Expiration|null $expiration = null,
        int $failedAttempts = 0,

exec
/bin/zsh -lc "sed -n '1,120p' tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php && sed -n '50,90p' tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php && sed -n '50,90p' tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php && sed -n '345,380p' tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class MediaConfigTest extends TestCase
{
    public function testRealMediaConfigIsMappedFromContainer(): void
    {
        $mediaConfig = $this->getContainer()->get(MediaConfig::class);

        self::assertSame(86_400, $mediaConfig->stagingTtlSeconds);
        self::assertSame(16_777_216, $mediaConfig->multipartThresholdBytes);
        self::assertSame(8_388_608, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('imagick', $mediaConfig->imageProcessingDriver);
        self::assertSame('/usr/bin/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/usr/bin/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(1800, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(0, $mediaConfig->ffmpegThreads);
    }

    public function testMapsMediaConfigSection(): void
    {
        $mediaConfig = $this->mapperFor([
            'stagingTtlSeconds' => 3600,
            'multipartThresholdBytes' => 20_971_520,
            'multipartPartSizeBytes' => 5_242_880,
            'imageProcessingDriver' => 'gd',
            'ffmpegBinaryPath' => '/opt/ffmpeg',
            'ffprobeBinaryPath' => '/opt/ffprobe',
            'ffmpegTimeoutSeconds' => 600,
            'ffmpegThreads' => 4,
        ])->map(section: MediaConfig::configName(), targetClass: MediaConfig::class);

        self::assertSame('media', MediaConfig::configName());
        self::assertSame(3600, $mediaConfig->stagingTtlSeconds);
        self::assertSame(20_971_520, $mediaConfig->multipartThresholdBytes);
        self::assertSame(5_242_880, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('gd', $mediaConfig->imageProcessingDriver);
        self::assertSame('/opt/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/opt/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(600, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(4, $mediaConfig->ffmpegThreads);
    }

    public function testRejectsMultipartThresholdSmallerThanPartSize(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('media.multipartThresholdBytes');

        new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 6_291_456,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[MediaConfig::configName(), $config]]);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}
    private function processor(): WaveformExposingAudioProcessor
    {
        return new WaveformExposingAudioProcessor(
            $this->createStub(MediaFileServiceContract::class),
            $this->mediaConfig(),
        );
    }

    private function mediaConfig(): MediaConfig
    {
        return new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
        );
    }
}
    public function testUnsupportedDriverThrows(): void
    {
        $this->expectException(MediaImageProcessorException::class);

        $this->processor('bogus');
    }

    /**
     * @return list<array{string}>
     */
    public static function driverProvider(): array
    {
        return [['imagick'], ['gd']];
    }

    private function processor(string $driver): ImagickMediaImageProcessor
    {
        return new ImagickMediaImageProcessor(new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: $driver,
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
        ));
    }

    private function jpegFixture(): string
    {
        return $this->encodeFixture(static fn($image): bool => \imagejpeg($image));
    }

    private function pngFixture(): string
    {
        return $this->encodeFixture(static fn($image): bool => \imagepng($image));
    }

    private function encodeFixture(callable $encode): string
    {
        \file_put_contents($sourceFile, 'source');

        return $sourceFile;
    }

    private function timeout(): ProcessTimedOutException
    {
        return new ProcessTimedOutException(new Process(['true']), ProcessTimedOutException::TYPE_GENERAL);
    }

    private function mediaConfig(int $ffmpegThreads = 0): MediaConfig
    {
        return new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: $ffmpegThreads,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' docker/test/assert-coverage.php && sed -n '1,220p' docker/test/run-qa.sh" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

if ($argc !== 3) {
    \fwrite(STDERR, "Использование: php docker/test/assert-coverage.php <clover.xml> <minimum>\n");
    exit(2);
}

$coverageFile = $argv[1];
$minimumCoverage = (float) $argv[2];

if (!\is_file($coverageFile)) {
    \fwrite(STDERR, \sprintf("Файл покрытия `%s` не найден.\n", $coverageFile));
    exit(2);
}

$coverage = \simplexml_load_file($coverageFile);

if (!$coverage instanceof SimpleXMLElement) {
    \fwrite(STDERR, \sprintf("Файл покрытия `%s` не удалось прочитать.\n", $coverageFile));
    exit(2);
}

$metrics = $coverage->xpath('/coverage/project/metrics')[0] ?? null;

if (!$metrics instanceof SimpleXMLElement) {
    \fwrite(STDERR, "В Clover-отчёте нет общих метрик покрытия.\n");
    exit(2);
}

$coveredStatements = (int) $metrics['coveredstatements'];
$statements = (int) $metrics['statements'];
$coveragePercent = $statements === 0 ? 100.0 : ($coveredStatements / $statements) * 100;

if ($coveragePercent < $minimumCoverage) {
    \fwrite(
        STDERR,
        \sprintf(
            "Покрытие %.2f%% ниже обязательного порога %.2f%%.\n",
            $coveragePercent,
            $minimumCoverage,
        ),
    );
    exit(1);
}

\printf("Покрытие %.2f%% соответствует порогу %.2f%%.\n", $coveragePercent, $minimumCoverage);
#!/usr/bin/env bash
set -euo pipefail

# Полный quality gate: стиль, PHPStan и один coverage-run (PCOV) без отдельного
# обычного прогона тестов перед покрытием. composer qa = cs + phpstan +
# test-coverage, поэтому набор тестов запускается ровно один раз с покрытием.

echo "[test-runner] Этап 1/4: очистка runtime-артефактов"
bash docker/test/clean-run-artifacts.sh

echo "[test-runner] Этап 2/4: миграции тестовых баз"
bash docker/test/migrate-test-databases.sh

echo "[test-runner] Этап 3/4: прогрев Cycle schema cache"
bash docker/test/warmup.sh

echo "[test-runner] Этап 4/4: стиль, PHPStan и один coverage-run (PCOV)"
COMPOSER_PROCESS_TIMEOUT=900 composer qa

echo "[test-runner] Composer QA завершён успешно"

exec
/bin/zsh -lc "rg -n \"GetUserPublicProfileHandler|GetUserPublicProfilesHandler|profileHandlerAssembler\\(|UserPublicProfileAssembler|/api/v1/users|public profile\" tests app/src/Modules/User app/src/Modules/Posts" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:7:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:14:final class GetUserPublicProfileHandlerTest extends UserApplicationTestCase
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:56:    private function handler(): GetUserPublicProfileHandler
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:58:        return new GetUserPublicProfileHandler(
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:60:            assembler: $this->profileHandlerAssembler(),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:21:use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:36: * UserPublicProfileAssembler со стабом файлового сервиса (URL предсказуем, без обращения к S3).
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:85:    protected function profileHandlerAssembler(): UserPublicProfileAssembler
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:90:        return new UserPublicProfileAssembler(
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php:8:use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php:17:final readonly class GetUserPublicProfileHandler
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php:21:        private UserPublicProfileAssembler $assembler,
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:22:final readonly class UserPublicProfileAssembler
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:8:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:12:final class GetUserPublicProfilesHandlerTest extends UserApplicationTestCase
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:40:    private function handler(): GetUserPublicProfilesHandler
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:42:        return new GetUserPublicProfilesHandler(
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:44:            assembler: $this->profileHandlerAssembler(),
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:9:use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:20:final readonly class GetUserPublicProfilesHandler
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:24:        private UserPublicProfileAssembler $assembler,
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:11:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:13:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:39:        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:40:        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:11:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:13:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:27:        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:28:        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:23:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
app/src/Modules/Posts/Application/View/PostViewAssembler.php:25:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
app/src/Modules/Posts/Application/View/PostViewAssembler.php:50:        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:51:        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,

exec
/bin/zsh -lc 'rg -n "UserController|GetUserPublicProfile|UserPublicProfile|profile" app/src/Modules/User/Presentation tests/Feature/Modules/User tests/Feature/Modules/Posts | head -200' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
rg: app/src/Modules/User/Presentation: No such file or directory (os error 2)
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:7:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:8:use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:14:final class GetUserPublicProfileHandlerTest extends UserApplicationTestCase
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:20:        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:33:        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:44:        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:53:        $this->handler()->handle(new GetUserPublicProfileQuery(UserId::generate()->value()));
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:56:    private function handler(): GetUserPublicProfileHandler
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:58:        return new GetUserPublicProfileHandler(
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:60:            assembler: $this->profileHandlerAssembler(),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:21:use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:36: * UserPublicProfileAssembler со стабом файлового сервиса (URL предсказуем, без обращения к S3).
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:50:            email: Email::fromString(\sprintf('profile.user%d@example.com', $this->userCounter)),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:51:            nickname: UserNickname::fromString(\sprintf('profile.user%d', $this->userCounter)),
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:85:    protected function profileHandlerAssembler(): UserPublicProfileAssembler
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:90:        return new UserPublicProfileAssembler(
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:7:use App\Modules\User\Application\Dto\UserPublicProfileView;
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:8:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:9:use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:12:final class GetUserPublicProfilesHandlerTest extends UserApplicationTestCase
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:19:        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:23:        self::assertCount(2, $profiles);
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:25:        $userIds = $profiles
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:26:            ->map(static fn(UserPublicProfileView $profile): string => $profile->userId)
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:35:        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(userIds: []));
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:37:        self::assertCount(0, $profiles);
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:40:    private function handler(): GetUserPublicProfilesHandler
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:42:        return new GetUserPublicProfilesHandler(
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:44:            assembler: $this->profileHandlerAssembler(),

exec
/bin/zsh -lc "rg -n \"new PostViewAssembler|PostViewAssembler|GetUserFeed|PostController|fromPost\\(\" tests/Feature/Modules/Posts tests/Feature/Modules | head -200" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:10:final class GetUserFeedHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:10:final class GetUserFeedHttpTest extends PostsHttpTestCase

codex
Есть ещё один скрытый риск: в модуле User нет HTTP-слоя, а application-тесты профиля собирают ассемблер вручную. Поэтому новый `UserBootloader` может остаться без прямого теста, если полагаться только на текущие тесты профиля.
exec
/bin/zsh -lc "sed -n '1,220p' tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Posts\Domain\Enum\PostStatus;

final class GetUserFeedHttpTest extends PostsHttpTestCase
{
    public function testFeedBatchesMediaAndTagsPerPost(): void
    {
        $author = $this->createUser();
        $firstMedia = $this->createReadyMedia($author->id, MediaVisibility::Public);
        $secondMedia = $this->createReadyMedia($author->id, MediaVisibility::Public);

        $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Первая запись',
            'mediaIds' => [$firstMedia->id->value()],
            'tags' => ['yoga'],
        ])->assertOk();
        $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Вторая запись',
            'mediaIds' => [$secondMedia->id->value()],
            'tags' => ['yoga', 'медитация'],
        ])->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        $byText = [];
        foreach ($this->json($response)['data'] as $post) {
            $byText[$post['text']] = $post;
        }

        self::assertCount(1, $byText['Первая запись']['media']);
        self::assertCount(1, $byText['Первая запись']['tags']);
        self::assertCount(1, $byText['Вторая запись']['media']);
        self::assertCount(2, $byText['Вторая запись']['tags']);
    }

    public function testOwnerSeesDraftsAndPublished(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        self::assertCount(2, $this->json($response)['data']);
    }

    public function testOwnerDoesNotSeeOwnBlockedPost(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);
        $this->persistPost($author->id, PostStatus::Blocked);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(2, $data);
        foreach ($data as $post) {
            self::assertNotSame('blocked', $post['status']);
        }
    }

    public function testStrangerSeesOnlyPublished(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $stranger->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertSame('published', $data[0]['status']);
    }

    public function testHidesSoftDeletedPosts(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Published, deleted: true);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        self::assertCount(1, $this->json($response)['data']);
    }

    public function testPaginatesWithCursor(): void
    {
        $author = $this->createUser();
        for ($index = 0; $index < 3; $index++) {
            $this->persistPost($author->id, PostStatus::Published);
        }

        $firstPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2', $author->id->value()),
            $author->id,
        );
        $firstPage->assertOk();
        $firstBody = $this->json($firstPage);
        self::assertCount(2, $firstBody['data']);
        self::assertNotNull($firstBody['meta']['nextCursor']);

        $secondPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2&cursor=%s', $author->id->value(), $firstBody['meta']['nextCursor']),
            $author->id,
        );
        $secondPage->assertOk();
        $secondBody = $this->json($secondPage);
        self::assertCount(1, $secondBody['data']);
        self::assertNull($secondBody['meta']['nextCursor']);
    }

    public function testExposesLikedByMeFlagPerPost(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $likedPost = $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $likedPost->id->value()), $viewer->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $viewer->id);

        $response->assertOk();
        $likedFlags = [];
        foreach ($this->json($response)['data'] as $post) {
            $likedFlags[$post['id']] = $post['likedByMe'];
        }

        self::assertTrue($likedFlags[$likedPost->id->value()]);
        self::assertContains(false, $likedFlags);
    }

    public function testFeedEnrichesOriginalsOfRepostsInListing(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $firstOriginal = $this->persistPost($author->id, PostStatus::Published);
        $secondOriginal = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $firstOriginal->id->value()), $reposter->id)
            ->assertOk();
        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $secondOriginal->id->value()), $reposter->id)
            ->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $reposter->id);

        $response->assertOk();
        $originalIds = [];
        foreach ($this->json($response)['data'] as $post) {
            self::assertNotNull($post['original']);
            $originalIds[] = $post['original']['id'];
        }

        self::assertContains($firstOriginal->id->value(), $originalIds);
        self::assertContains($secondOriginal->id->value(), $originalIds);
    }

    public function testFeedReturnsNullOriginalWhenAllRepostOriginalsInvisible(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertOk();

        // Оригинал удалён после репоста — в ленте репостера original репоста становится null.
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $reposter->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertNull($data[0]['original']);
    }

    public function testEmptyFeedReturnsEmptyData(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $viewer->id);

        $response->assertOk();
        self::assertCount(0, $this->json($response)['data']);
        self::assertNull($this->json($response)['meta']['nextCursor']);
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/user/%s', $author->id->value()))->assertUnauthorized();
    }
}

exec
/bin/zsh -lc 'find tests/Feature/Modules/Posts -type f -maxdepth 3 | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/CommentHttpTest.php
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php
tests/Feature/Modules/Posts/Http/PostsOpenApiGenerationTest.php
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php
tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
tests/Feature/Modules/Posts/Repository/LikesAndMentionsRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostBlockRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php

exec
/bin/zsh -lc "rg -n \"getContainer\\(\\)->get\\(|new GetUserFeedHandler|new PostViewAssembler|PostViewAssembler|PostsHttpTestCase\" tests/Feature/Modules/Posts -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:10:final class CommentHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:10:final class GetUserFeedHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:10:final class PostReactionHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:10:final class CommentReactionHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:52:abstract class PostsHttpTestCase extends DatabaseTestCase
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:162:        return $this->getContainer()->get(EntityManagerInterface::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:167:        return $this->getContainer()->get(PostRepository::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:172:        return $this->getContainer()->get(PostTagRepository::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:177:        return $this->getContainer()->get(PostMentionRepository::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:182:        return $this->getContainer()->get(PostLikeRepository::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:269:        return $this->getContainer()->get(CommentRepository::class);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:274:        return $this->getContainer()->get(CommentLikeRepository::class);
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:11:final class RepostPostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:10:final class PublishPostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:10:final class DeletePostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:13:final class CreatePostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:13:final class GetPostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:67:        $media = $this->getContainer()->get(MediaRepository::class)->findById($mediaId);
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:10:final class GetCommentRepliesHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:10:final class GetPostCommentsHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:43:        return $this->getContainer()->get(EntityManagerInterface::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:86:        return $this->getContainer()->get(PostRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:91:        return $this->getContainer()->get(CommentRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:96:        return $this->getContainer()->get(PostMediaRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:101:        return $this->getContainer()->get(PostLikeRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:106:        return $this->getContainer()->get(PostMentionRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:111:        return $this->getContainer()->get(PostTagRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:116:        return $this->getContainer()->get(PostBlockRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:121:        return $this->getContainer()->get(CommentLikeRepository::class);
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:126:        return $this->getContainer()->get(CommentMentionRepository::class);

exec
/bin/zsh -lc "sed -n '1,220p' tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php && sed -n '1,180p' app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Configuration\TypedConfig;
use Spiral\Config\ConfiguratorInterface;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

final class ConfigShapeTest extends TestCase
{
    public function testConfigFilesAreExpectedTypedConfigSections(): void
    {
        self::assertSame(
            [
                'cache',
                'centrifugo',
                'cycle',
                'database',
                'locale',
                'mailer',
                'media',
                'migration',
                'openapi',
                'outbox',
                'push',
                'queue',
                'scaffolder',
                'session',
                'storage',
                'translator',
                'user',
            ],
            $this->configFileSections(),
        );
    }

    public function testRootTypedConfigsMatchConfigFiles(): void
    {
        self::assertSame($this->configFileSections(), $this->typedConfigSections());
    }

    public function testSafeConfigShapeHelperDoesNotExposeSecretValues(): void
    {
        $shape = $this->configShape('storage');

        self::assertSame('string', $shape['servers']['s3']['key']);
        self::assertSame('string', $shape['servers']['s3']['secret']);
        self::assertSame('null', $shape['servers']['s3']['token']);
        self::assertSame('bool', $shape['servers']['s3']['options']['use_path_style_endpoint']);
        self::assertNotContains('yoga_loka_password', $this->flattenShape($shape));
    }

    public function testExistingTypedConfigsMapFromRealConfigurator(): void
    {
        $container = $this->getContainer();
        $configMapper = $container->get(ConfigMapper::class);

        $cacheConfig = $configMapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
        $openApiConfig = $configMapper->map(section: OpenApiConfig::configName(), targetClass: OpenApiConfig::class);

        self::assertSame('local', $cacheConfig->default);
        self::assertSame('/api/v1', $openApiConfig->routePrefix);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function configShape(string $section): array
    {
        $config = $this->getContainer()
            ->get(ConfiguratorInterface::class)
            ->getConfig($section);

        if (!\is_array($config)) {
            return ['root' => \get_debug_type($config)];
        }

        return $this->describeConfigValue($config);
    }

    /**
     * @return list<string>
     */
    private function configFileSections(): array
    {
        $sections = \array_map(
            static fn(string $file): string => \basename($file, '.php'),
            \glob($this->rootDirectory() . '/app/config/*.php') ?: [],
        );
        \sort($sections);

        return \array_values($sections);
    }

    /**
     * @return list<string>
     */
    private function typedConfigSections(): array
    {
        $sections = [];
        $finder = Finder::create()
            ->files()
            ->in($this->rootDirectory() . '/app/src/Shared/Infrastructure/Configuration')
            ->name('*Config.php')
            ->sortByName();

        foreach ($finder as $file) {
            $configClass = 'App\\Shared\\Infrastructure\\Configuration\\' . \str_replace(
                search: ['/', '.php'],
                replace: ['\\', ''],
                subject: $file->getRelativePathname(),
            );

            if (!\is_subclass_of(object_or_class: $configClass, class: TypedConfig::class)) {
                continue;
            }

            /** @var class-string<TypedConfig> $configClass */
            $sections[] = $configClass::configName();
        }

        \sort($sections);

        return \array_values($sections);
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     */
    private function describeConfigValue(array $config): array
    {
        $shape = [];

        foreach ($config as $key => $value) {
            $shape[$key] = \is_array($value)
                ? $this->describeConfigValue($value)
                : \get_debug_type($value);
        }

        return $shape;
    }

    /**
     * @param array<array-key, mixed> $shape
     * @return list<string>
     */
    private function flattenShape(array $shape): array
    {
        $values = [];

        foreach ($shape as $value) {
            if (\is_array($value)) {
                $values = [...$values, ...$this->flattenShape($value)];

                continue;
            }

            $values[] = (string) $value;
        }

        return $values;
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Framework\DirectoryAlias;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Symfony\Component\Finder\Finder;

final class ConfigBootloader extends Bootloader
{
    private const string CONFIGURATION_DIRECTORY = 'app/src/Shared/Infrastructure/Configuration';
    private const string CONFIGURATION_NAMESPACE_PREFIX = 'App\\Shared\\Infrastructure\\Configuration\\';
    private const string CONFIG_FILE_PATTERN = '*Config.php';

    public function __construct(
        private readonly DirectoriesInterface $directories,
    ) {}

    /**
     * @param  ConfiguratorInterface<object>  $configurator
     */
    public function configMapper(ConfiguratorInterface $configurator): ConfigMapper
    {
        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ConfigMapper::class => [self::class, 'configMapper'],
            ...$this->configSingletons(),
        ];
    }

    /**
     * @return array<class-string<TypedConfig>, callable(ConfigMapper): object>
     */
    private function configSingletons(): array
    {
        $singletons = [];
        $finder = Finder::create()
            ->files()
            ->in(
                \sprintf(
                    '%s/%s',
                    \rtrim(
                        string: $this->directories->get(name: DirectoryAlias::Root->value),
                        characters: \DIRECTORY_SEPARATOR,
                    ),
                    self::CONFIGURATION_DIRECTORY,
                ),
            )
            ->name(self::CONFIG_FILE_PATTERN)
            ->sortByName();

        foreach ($finder as $file) {
            $configClass = self::CONFIGURATION_NAMESPACE_PREFIX . \str_replace(
                search: ['/', '.php'],
                replace: ['\\', ''],
                subject: $file->getRelativePathname(),
            );

            if (!\is_subclass_of(object_or_class: $configClass, class: TypedConfig::class)) {
                continue;
            }

            $singletons[$configClass] = static fn(ConfigMapper $configMapper): object => $configMapper->map(
                section: $configClass::configName(),
                targetClass: $configClass,
            );
        }

        return $singletons;
    }
}

exec
/bin/zsh -lc "rg -n \"use App\\\\Shared\\\\Infrastructure\\\\Configuration|Config\" app/src/Modules/System app/src/Modules/*/Presentation app/src/Modules/*/Application | head -120" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
rg: regex parse error:
    (?:use App\Shared\Infrastructure\Configuration|Config)
                     ^^
error: unrecognized escape sequence

codex
Проверка документов дала более серьёзный риск: план сам одновременно хочет «только Infrastructure» и оставляет Presentation с конфигами. Это надо формулировать очень аккуратно, иначе после правки правил код всё равно будет нарушать новые правила.
exec
/bin/zsh -lc "rg -n 'use App\\\\Shared\\\\Infrastructure\\\\Configuration|Config' app/src/Modules/System app/src/Modules/*/Presentation app/src/Modules/*/Application" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:7:use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:23:        OpenApiConfig $openApiConfig,
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:27:        if (!$openApiConfig->enabled) {
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:35:                config: $openApiConfig->toGeneratorConfig(projectRoot: $directories->get(name: DirectoryAlias::Root->value)),
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:7:use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:23:        OpenApiConfig $openApiConfig,
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:27:        if (!$openApiConfig->enabled) {
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:35:                config: $openApiConfig->toGeneratorConfig(projectRoot: $directories->get(name: DirectoryAlias::Root->value)),
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:8:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:14: * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:23:        private LocaleConfig $localeConfig,
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:50:        return \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php:52:            : $this->localeConfig->default;
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:10:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:10:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:9:use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:21:        private OpenApiConfig $openApiConfig,
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:30:        if (!$this->openApiConfig->swaggerEnabled) {
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:41:        if (!$this->openApiConfig->swaggerEnabled) {
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:45:        $openApiFile = $this->openApiConfig->outputFile(
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:15:use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:28:        private LocaleConfig $localeConfig,
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:69:     * Нормализует локаль запроса: неподдерживаемое значение заменяется на LocaleConfig.default,
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:74:        $supported = \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php:76:            : $this->localeConfig->default;
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:9:use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:21:        private OpenApiConfig $openApiConfig,
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:30:        if (!$this->openApiConfig->swaggerEnabled) {
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:41:        if (!$this->openApiConfig->swaggerEnabled) {
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:45:        $openApiFile = $this->openApiConfig->outputFile(
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:11:use App\Shared\Infrastructure\Configuration\User\UserConfig;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:16: * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:30:        private UserConfig $userConfig,
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:48:            return $this->userConfig->defaultAvatarUrl;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:58:            return $this->userConfig->defaultAvatarUrl;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:19: * Реальное имя бакета и prefix реализация резолвит из StorageConfig по алиасу MediaStorage,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:22:use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:32:        private MediaConfig $mediaConfig,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:52:            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:93:        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:152:        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;

exec
/bin/zsh -lc "sed -n '1,80p' app/src/Modules/System/Presentation/Http/Controller/HealthController.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\Enum\HealthStatus;
use App\Modules\System\Presentation\Http\Resource\HealthResource;
use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\DataResponse;

final class HealthController
{
    /**
     * Проверка работоспособности API.
     *
     * @return DataResponse<HealthResource>
     */
    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
    #[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
    public function show(): DataResponse
    {
        return new DataResponse(new HealthResource(status: HealthStatus::Ok));
    }
}

exec
/bin/zsh -lc "sed -n '1,120p' app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,100p' app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Console;

use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use GianTiaga\SpiralOpenApi\Exception\OpenApiException;
use GianTiaga\SpiralOpenApi\OpenApiGenerator;

#[AsCommand(
    name: 'openapi:generate',
    description: 'Сгенерировать OpenAPI YAML из типизированного HTTP-слоя',
)]
final class OpenApiGenerateCommand extends Command
{
    public function perform(
        OpenApiConfig $openApiConfig,
        DirectoriesInterface $directories,
        OpenApiGenerator $openApiGenerator,
    ): int {
        if (!$openApiConfig->enabled) {
            $this->warning('Генерация OpenAPI выключена в конфигурации.');

            return SymfonyCommand::SUCCESS;
        }

        try {
            $openApiGenerationResult = $openApiGenerator->generate(
                config: $openApiConfig->toGeneratorConfig(projectRoot: $directories->get(name: DirectoryAlias::Root->value)),
                debugWriter: fn(string $message): null => $this->writeDebug(message: $message),
            );
        } catch (OpenApiException $exception) {
            $this->error('Ошибка генерации OpenAPI: ' . $exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $this->info(\strtr(
            string: 'OpenAPI YAML записан: {file}. Операций: {operations}, schemas: {schemas}.',
            from: [
                '{file}' => $openApiGenerationResult->outputFile,
                '{operations}' => (string) $openApiGenerationResult->operationCount,
                '{schemas}' => (string) $openApiGenerationResult->schemaCount,
            ],
        ));

        return SymfonyCommand::SUCCESS;
    }

    private function writeDebug(string $message): null
    {
        $this->comment('[openapi] ' . $message);

        return null;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\View\SwaggerView;
use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\FileContentResponse;
use GianTiaga\SpiralOpenApi\Response\HtmlResponse;

final readonly class SwaggerController
{
    public function __construct(
        private OpenApiConfig $openApiConfig,
        private DirectoriesInterface $directories,
        private SwaggerView $swaggerView,
    ) {}

    #[Route(route: '/api/docs', name: 'api.docs', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function index(): HtmlResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException('app.system.swagger_ui_disabled');
        }

        return new HtmlResponse(html: $this->swaggerView->render());
    }

    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function spec(): FileContentResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException('app.system.swagger_ui_disabled');
        }

        $openApiFile = $this->openApiConfig->outputFile(
            projectRoot: $this->directories->get(name: DirectoryAlias::Root->value),
        );

        if (!\is_file($openApiFile)) {
            throw new NotFoundException('app.system.openapi_yaml_not_generated');
        }

        return new FileContentResponse(
            content: (string) \file_get_contents($openApiFile),
            contentType: ContentType::Yaml,
        );
    }
}

exec
/bin/zsh -lc 'rg -n "CacheConfig" app/src/Modules/System app/src tests' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:10:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:7:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig as AppCacheConfig;
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:10:use Spiral\Cache\Config\CacheConfig as SpiralCacheConfig;
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:13:final class CacheConfigBindingTest extends TestCase
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:15:    public function testCacheConfigIsRegisteredAsSingleton(): void
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:19:        $firstConfig = $container->get(AppCacheConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:20:        $secondConfig = $container->get(AppCacheConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:42:    public function testSpiralCacheConfigStillUsesNativeArrayConfig(): void
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php:44:        $spiralConfig = $this->getContainer()->get(SpiralCacheConfig::class);
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:10:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:7:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:64:        $cacheConfig = $configMapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:8:use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:23:    public function testHydratesCacheConfig(): void
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:49:        $config = $mapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:72:        $this->expectExceptionMessage(CacheConfig::class);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:74:        $mapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:119:    public function testNormalizesCacheConfig(): void
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:122:        $config = new CacheConfig(
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:204:        return $this->mapperForSection(section: CacheConfig::configName(), config: $config);
app/src/Shared/Infrastructure/Configuration/Cache/CacheConfig.php:9:final readonly class CacheConfig implements TypedConfig

codex
- [архитектура] В плане остаётся конфликт: правило «`*Config` читаются только в Infrastructure», но `SwaggerController` и `OpenApiGenerateCommand` в Presentation продолжают читать `OpenApiConfig`; `HealthController` сейчас не инжектит конфиг, там только неиспользуемый импорт `CacheConfig` — либо расширить рефактор на Presentation, либо в `rules.md`/`arch.md` явно писать только про запрет для Domain/Application.

- [документация] В формулировке для `rules.md` перечислены «бутлоадеры/инфра-сервисы», но `LocaleMiddleware` тоже законно читает `LocaleConfig` — добавить `middleware` или написать шире: «Infrastructure-код».

- [архитектура] План предлагает обновить докблок `MediaFileServiceContract` текстом про значение из конфига, но это файл Application-слоя; так Application снова начинает «знать» источник значения — писать нейтрально: «срок передаёт `MediaUrlService`, значение по умолчанию приходит извне при сборке сервиса».

- [проверки] Финальный критерий требует `make test` и `make phpstan`, но 100% покрытие проверяется только через `make test-coverage`/`make qa` — добавить финальный обязательный `make qa` или минимум `make test-coverage`, иначе новые фабрики/классы могут пройти план, но упасть на настоящем gate.

- [тесты] Новый `UserBootloader` лучше покрыть прямым Kernel-тестом на резолв `UserPublicProfileAssembler`; текущие User application-тесты собирают ассемблер вручную, а косвенное покрытие через Posts HTTP слишком хрупкое.

- [скрытый риск] `LocaleResolver` централизует локаль, но не закрывает старую дыру: `LocaleConfig.supported/default` могут содержать значение, которого нет в enum `Locale`, и `CreateUserHandler` упадёт на `Locale::from()` — добавить проверку в `LocaleResolver` или отдельный тест/валидацию конфига для совместимости с enum.

- [документация] Финальный grep ищет только `use App\Shared\Infrastructure\Configuration` в Application, но после рефактора могут остаться текстовые ссылки `LocaleConfig.default`, `UserConfig`, `StorageConfig`, `MediaConfig` в докблоках Application — расширить проверку на `rg "Config|Configuration" app/src/Modules/*/Application`.

- [тесты] В `SendLoginCodeHandlerTest` импорт `LocaleConfig` после замены конструктора станет лишним; план явно предупреждает не удалять импорт в `CreateUserHandlerTest`, но не говорит удалить его здесь — добавить в фазу 2, иначе это поймает только стиль/статический анализ.

ВЕРДИКТ: нужны правки — направление верное, но план сейчас может создать противоречивые правила и не закрывает настоящий gate покрытия.
tokens used
128 628
- [архитектура] В плане остаётся конфликт: правило «`*Config` читаются только в Infrastructure», но `SwaggerController` и `OpenApiGenerateCommand` в Presentation продолжают читать `OpenApiConfig`; `HealthController` сейчас не инжектит конфиг, там только неиспользуемый импорт `CacheConfig` — либо расширить рефактор на Presentation, либо в `rules.md`/`arch.md` явно писать только про запрет для Domain/Application.

- [документация] В формулировке для `rules.md` перечислены «бутлоадеры/инфра-сервисы», но `LocaleMiddleware` тоже законно читает `LocaleConfig` — добавить `middleware` или написать шире: «Infrastructure-код».

- [архитектура] План предлагает обновить докблок `MediaFileServiceContract` текстом про значение из конфига, но это файл Application-слоя; так Application снова начинает «знать» источник значения — писать нейтрально: «срок передаёт `MediaUrlService`, значение по умолчанию приходит извне при сборке сервиса».

- [проверки] Финальный критерий требует `make test` и `make phpstan`, но 100% покрытие проверяется только через `make test-coverage`/`make qa` — добавить финальный обязательный `make qa` или минимум `make test-coverage`, иначе новые фабрики/классы могут пройти план, но упасть на настоящем gate.

- [тесты] Новый `UserBootloader` лучше покрыть прямым Kernel-тестом на резолв `UserPublicProfileAssembler`; текущие User application-тесты собирают ассемблер вручную, а косвенное покрытие через Posts HTTP слишком хрупкое.

- [скрытый риск] `LocaleResolver` централизует локаль, но не закрывает старую дыру: `LocaleConfig.supported/default` могут содержать значение, которого нет в enum `Locale`, и `CreateUserHandler` упадёт на `Locale::from()` — добавить проверку в `LocaleResolver` или отдельный тест/валидацию конфига для совместимости с enum.

- [документация] Финальный grep ищет только `use App\Shared\Infrastructure\Configuration` в Application, но после рефактора могут остаться текстовые ссылки `LocaleConfig.default`, `UserConfig`, `StorageConfig`, `MediaConfig` в докблоках Application — расширить проверку на `rg "Config|Configuration" app/src/Modules/*/Application`.

- [тесты] В `SendLoginCodeHandlerTest` импорт `LocaleConfig` после замены конструктора станет лишним; план явно предупреждает не удалять импорт в `CreateUserHandlerTest`, но не говорит удалить его здесь — добавить в фазу 2, иначе это поймает только стиль/статический анализ.

ВЕРДИКТ: нужны правки — направление верное, но план сейчас может создать противоречивые правила и не закрывает настоящий gate покрытия.
