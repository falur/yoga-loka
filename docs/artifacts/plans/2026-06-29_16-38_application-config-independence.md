---
title: Application не зависит от конфига — чтение TypedConfig только в Infrastructure (+ задача про presigned TTL)
date: 2026-06-29 16:38
mode: strict
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
  Infrastructure-коде (бутлоадеры/инфра-сервисы/middleware); **Domain и Application `*Config` не импортируют**
  и получают готовые значения (VO/скаляры/доменные сервисы/settings) через DI».
- Примечание по охвату (в плане, не в доках): `HealthController` сейчас содержит лишь **неиспользуемый**
  импорт `CacheConfig` (фактической зависимости от конфига нет), `SwaggerController`/`OpenApiGenerateCommand`
  читают `OpenApiConfig` — всё это Presentation и вне охвата. Поэтому правило и сформулировано про
  Application/Domain, чтобы не сделать Presentation доковым нарушителем. Неиспользуемый импорт в
  `HealthController` — мелкая чистка вне этой задачи (не трогаем).

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
- `tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php` (~стр. 74–77): то же.
- `tests/Feature/Modules/User/Application/CreateUserHandlerTest.php` (~стр. 115–120): то же. **Импорт
  `use ...\LocaleConfig` НЕ удалять** — он ещё используется в ассерте (`...->get(LocaleConfig)->default`,
  ~стр. 63–64). Аналогично проверить ассерты в `SendLoginCodeHandlerTest`.
- `tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php` (~стр. 52–57, метод `createUserHandler()`,
  используется в `CompleteRegistrationHandlerTest`): то же.
- В каждом из этих файлов после замены аргумента проверить импорт `use ...\LocaleConfig`: **убрать**, если
  он больше не используется (например, в `SendLoginCodeHandlerTest`), и **оставить**, если ещё нужен в
  ассертах (`CreateUserHandlerTest` — `...->get(LocaleConfig)->default`). Висячий импорт недопустим
  (правило «Нет мёртвого кода»/PHPStan).
- Добавить юнит-тест `LocaleResolver` (поддерживаемая/неподдерживаемая/пустая локаль).
- Добавить Kernel-тест биндинга: `$this->getContainer()->get(LocaleResolver::class)` резолвится и
  `resolve(...)` работает — гарантирует исполнение тела фабрики `AppBootloader::localeResolver()`.
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
- Добавить Kernel-тест биндинга: `$this->getContainer()->get(UserPublicProfileAssembler::class)` резолвится —
  гарантирует исполнение тела фабрики `UserBootloader::userPublicProfileAssembler()` (косвенное покрытие
  через HTTP-профиль хрупкое, явный Kernel-тест надёжнее).
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
- Обновить докблок `MediaFileServiceContract` (строка ~20) **нейтрально**, без упоминания конфига (это
  Application-слой, он не должен «знать» источник): срок presigned-скачивания задаёт `MediaUrlService`,
  значение по умолчанию приходит извне при сборке сервиса, вызывающий может переопределить.
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
- Добавить Kernel-тест биндинга: `$this->getContainer()->get(MediaUrlService::class)` резолвится —
  гарантирует исполнение тела фабрики `MediaBootloader::mediaUrlService()` (с проверкой диапазона
  `MediaPresignedTtl` из конфига).
- `make phpstan` зелёный; `make test` зелёный.

### 6. Финальная проверка строгости правила

Цель: убедиться, что правило выполнено целиком и нет висячих ссылок.

Что сделать:
- Поиск по репозиторию: ни один файл под `app/src/Modules/*/Application/**` не импортирует
  `App\Shared\Infrastructure\Configuration\` (`grep -rn "use App\\Shared\\Infrastructure\\Configuration" app/src/Modules/*/Application`).
- Расширенный поиск текстовых упоминаний в Application (докблоки, комментарии): `grep -rn "Config\|Configuration" app/src/Modules/*/Application`
  — глазами проверить, что не осталось ссылок на `*Config` (`LocaleConfig.default`, `UserConfig`, `MediaConfig`
  и т.п.); легальные совпадения вроде `MediaUploadSettings`/`MediaConfig` в неудалённом коде допустимы.
- Нет ссылок на удалённое/старое: `MediaUrlResolverFactory`, `AVATAR_URL_TTL_SECONDS`,
  `POST_MEDIA_URL_TTL_SECONDS`, `FindMediaUrl(mediaId, presignedTtlSeconds)` и тексты «TTL … в конфиге нет».

Результат: правило соблюдено, мёртвых ссылок нет.

Сценарии тестирования: не применимо (проверочная фаза).

Проверка:
- Грепы пустые/проверены глазами (кроме самого файла плана).
- **`make qa` зелёный** (включает `make test-coverage` со 100%-гейтом и `make phpstan`). Именно `make qa`/
  `make test-coverage` проверяет покрытие — `make test` сам по себе гейт покрытия не закрывает, поэтому
  финальная проверка обязательно гоняет `make qa`.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза (кроме доковой 1 и проверочной 6) завершается обновлением своих
тестов и `make test` + `make phpstan`; **финальная фаза 6 гоняет `make qa`** (100%-гейт покрытия +
phpstan). Новые тесты: юнит `LocaleResolver`; значение по умолчанию presigned TTL + private-override-`0`-исключение;
и **Kernel-тест биндинга на каждую новую фабрику бутлоадера** — `LocaleResolver` (фаза 2),
`UserPublicProfileAssembler` (фаза 3), `MediaUploadSettings` (фаза 4), `MediaUrlService` (фаза 5). Явные
Kernel-тесты надёжнее косвенного HTTP-покрытия и гарантируют исполнение тел фабрик, иначе 100%-гейт
`make test-coverage` упадёт. Удаляемый код (фабрика `MediaUrlResolverFactory`) уходит вместе со своим путём.

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

## Реакция на ревью

Кросс-CLI ревью (codex, strict). Файл: `docs/plans/2026-06-29_16-38_application-config-independence_review.md`.
Codex искал НОВОЕ поверх мета-ревью; учтено:

- **Принято:** финальная фаза гоняет `make qa` (реальный 100%-гейт — `make test` его не закрывает).
- **Принято:** формулировка `rules.md` расширена до «Infrastructure-код (бутлоадеры/инфра-сервисы/middleware)»
  — `LocaleMiddleware` законно читает конфиг.
- **Принято:** докблок `MediaFileServiceContract` пишем нейтрально, без упоминания конфига (Application не
  должен «знать» источник значения).
- **Принято:** на каждую новую фабрику бутлоадера — прямой Kernel-тест резолва (надёжнее хрупкого
  косвенного HTTP-покрытия).
- **Принято:** финальный греп расширен на текстовые упоминания `Config` в докблоках Application, не только
  на `use ...Configuration`.
- **Принято:** в фазе 2 явно указано убрать ставший лишним импорт `LocaleConfig` в `SendLoginCodeHandlerTest`
  (и сохранить там, где он ещё в ассертах).
- **Принято к сведению:** `HealthController` содержит лишь неиспользуемый импорт `CacheConfig` (не реальная
  зависимость); правило сформулировано про Application/Domain, Presentation — вне охвата (решение пользователя).
- **Отложено (follow-up, вне охвата):** пред­существующая дыра `LocaleConfig.supported/default` ↔ enum
  `Locale` — значение вне enum уронит `CreateUserHandler::Locale::from()`. Рефактор поведение не меняет
  (как было, так и осталось). Закрытие (валидация `LocaleConfig` на членство в enum `Locale` или проверка в
  `LocaleResolver`) — отдельная задача; пользователь ограничил охват 4 Application-классами, в этот план не
  тащим, но фиксируем как известный риск.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-29_17-50_application-config-independence.md`

- [x] Шаг 1: arch.md + rules.md — строгое правило (Application/Domain не зависят от *Config)
- [x] Шаг 2: LocaleResolver + убрать LocaleConfig из SendLoginCodeHandler и CreateUserHandler
- [x] Шаг 3: UserBootloader + убрать UserConfig из UserPublicProfileAssembler
- [x] Шаг 4: MediaUploadSettings + убрать MediaConfig из RequestMediaUploadHandler
- [x] Шаг 5: presigned TTL по умолчанию + удалить MediaUrlResolverFactory
- [x] Шаг 6: финальная проверка строгости правила (грепы + make qa)
