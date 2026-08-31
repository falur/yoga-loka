---
title: Многоязычные ошибки API — перевод на границе + выбор языка по Accept-Language
date: 2026-06-11 22:30
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers: [claude-haiku, claude-sonnet, claude-opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-11_20-28_mnogoyazychnye-oshibki-api.md
---

# План реализации

## Задача

Приложение становится многоязычным. Ошибки 4xx, которые API отдаёт наружу, должны
приходить пользователю на его языке (`Accept-Language`), а не захардкоженным русским
текстом. Готово, когда:

1. Доменные 4xx-исключения несут ключ перевода + параметры, а перевод выполняется
   один раз на внешней границе (`ApiExceptionInterceptor`) в локали текущего запроса.
2. Локаль запроса определяется новым `LocaleMiddleware` из `Accept-Language`,
   пересечённого с белым списком поддерживаемых локалей, иначе — default.
3. Каталоги `app/locale/{ru,en}/messages.php` заполнены ключами всех мигрированных
   точек выброса; ответ ошибки приходит на нужном языке (en/ru), при неизвестном
   языке — на default.
4. Весь сьют (`make test`, `make phpstan`, тесты пакета) зелёный, покрытие 100%.

## Контекст

Подтверждённые фактом кода исходные состояния:

- `ApiExceptionInterceptor` (пакет `packages/spiral-api-errors`) уже получает
  `TranslatorInterface`, но для 4xx возвращает `getMessage()` без перевода
  (`src/Interceptor/ApiExceptionInterceptor.php:39`). Для 500 и route-not-found
  перевод по ключам уже работает.
- Доменные исключения `NotFoundException`/`ForbiddenException`/`ValidationException`/
  `AuthenticationException` (`app/src/Shared/Domain/Exception/*.php`) принимают сырой
  `string $message`, код зашит в конструкторе. `InvalidDomainValueException` (500) —
  не отдаётся наружу, не трогаем.
- Точек выброса 4xx — **23**: 11 `NotFound` (включая 3 в `SwaggerController`),
  9 `Validation`, 3 `Forbidden`, `Authentication` пока не выбрасывается. Часть
  использует именованный `message:` (`SwaggerController`), часть — позиционный
  (`Media`-хендлеры), часть с параметром через `sprintf` (`MediaTypeResolver`,
  `RequestMediaUploadHandler:128`).
- `app/locale/{ru,en}/` существуют, но пусты; подключены к translator через
  `directory('locale')`. Каталоги пакета используют домен `messages` с префиксом
  `gian_tiaga.spiral_api_errors.*` — коллизий с `app.*` нет.
- `app/config/translator.php`: баг — `fallbackLocale => env('LOCALE','en')` читает ту же
  переменную `LOCALE`, что и `locale`. `LOCALE=en` задан в `.env`/`.env.sample`.
- Конфига `app/config/locale.php` и `LocaleConfig` нет. `ConfigBootloader`
  авто-регистрирует любой `*Config.php` в `Shared/Infrastructure/Configuration`.
- Spiral translator интерполирует фигурными скобками `{name}` (`Translator::interpolate`),
  не `%name%`. `AcceptHeader::fromString()` + `getAll()` (сортировка по quality) +
  `AcceptHeaderItem::getValue(): ?string` (**nullable**) + `getQuality(): float` — готовый
  парсер заголовка.
- **`setLocale()` НЕТ в `Spiral\Translator\TranslatorInterface`**: он расширяет только
  `Symfony\Contracts\Translation\TranslatorInterface` (там только `trans`/`getLocale`).
  `setLocale()` объявлен в `LocaleAwareInterface` и реализован в конкретном
  `Spiral\Translator\Translator`. Поэтому middleware инжектит **конкретный**
  `Spiral\Translator\Translator`, иначе PHPStan level max падает на «undefined method».
- Уже есть typed-config `App\Shared\Infrastructure\Configuration\Translator\TranslatorConfig`
  (секция `translator`, поля `locale`/`fallbackLocale`/…) и образец `list<string>` —
  `TranslatorDomainConfig`. Есть пустая папка `app/src/Shared/Infrastructure/Framework/Middleware`
  (конвенциональное место middleware рядом с `Framework/Bootloader`).
- Существующие тесты, затрагиваемые миграцией (помимо HTTP-тестов): юнит
  `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php` конструирует все 4
  мигрируемых исключения именованным `message:` (строки 18/26/34/42) — сломается при rename
  параметра; `tests/Kernel/.../Configuration/ConfigShapeTest.php` (жёсткий sorted-список
  секций) и `SimpleConfigBindingTest.php` (ассертит `translatorConfig->locale === 'en'`,
  fallback не ассертит).
- Тесты: `TestCase::setUp()` принудительно ставит локаль `en` для каждого теста
  (`tests/TestCase.php:53`). HTTP-тесты только в `ApiErrorHttpTest` и `OpenApiHttpTest`.
  Тестовые роуты ошибок — `tests/App/.../ApiErrorTestController.php` +
  `ApiErrorTestRoutesBootloader.php`. `FakeHttp` поддерживает
  `->withHeader('Accept-Language', ...)`.

## Принятые решения

Все существенные решения подтверждены пользователем в research (раздел «Ответы на вопросы»):

- **Где переводить** — на внешней границе (`ApiExceptionInterceptor`), один раз, в
  локали запроса. Бизнес-код остаётся локаль-агностичным. Источник: ответ пользователя.
- **Как нести сообщение** — типизированный интерфейс `TranslatableException`
  (`translationKey()` + `translationParameters()`). Источник: ответ пользователя.
- **Где интерфейс** — в пакете `spiral-api-errors` (владелец рендеринга ошибок).
  `Shared/Domain` осознанно берёт зависимость на marker-интерфейс first-party пакета
  без framework-типов. Источник: ответ пользователя.
- **Источник локали** — `Accept-Language ∩ supported → default`, без завязки на профиль
  пользователя (модуля User/Auth нет). Источник: ответ пользователя.
- **Языки** — `ru` (default + fallback) + `en`. Источник: ответ пользователя.
- **Размер плана** — `normal` (5 фаз). Источник: ответ пользователя в текущем запросе.
- **Default-локаль резолва** (`decision_mode: autonomous`, причина): `LocaleConfig.default`
  читается как `env('LOCALE','ru')` — единый источник базовой локали с тем же `LOCALE`,
  что и translator. Code-default `ru` совпадает с решением research; в dev/test текущий
  `.env` (`LOCALE=en`) делает эффективный default `en`. Юнит/интеграционные тесты задают
  `LocaleConfig`/`Accept-Language` явно, не полагаясь на ambient-default.

Новые зависимости не добавляются: используются существующие `Spiral\Translator`,
`Spiral\Http\Header\AcceptHeader`, `CuyZ\Valinor` (config mapper). Версии не меняются.

## Целевой алгоритм

```text
HTTP Request (Accept-Language: en-US,en;q=0.9,de;q=0.5)
  -> ErrorHandlerMiddleware
  -> LocaleMiddleware (НОВЫЙ)
       AcceptHeader::fromString(...)->getAll()  // отсортировано по quality
       первый primary-subtag ∈ LocaleConfig.supported  ?  setLocale(match)
                                                        :  setLocale(LocaleConfig.default)
       DEBUG-лог: {acceptLanguage, resolvedLocale, usedFallback}
  -> RouteNotFoundMiddleware -> JsonPayloadMiddleware -> ...
  -> Controller -> Filter -> CommandBus/QueryBus -> Handler
       throw new NotFoundException('app.media.not_found')                       // только ключ
       throw new ValidationException(translationKey: 'app.media.unsupported_file_type',
                                     translationParameters: ['type' => $value]) // ключ + параметры
  -> ApiExceptionInterceptor (граница)
       $e instanceof TranslatableException
         ? translator->trans($e->translationKey(), $e->translationParameters())  // в локали запроса
         : $e->getMessage()                                                       // прежнее поведение
  -> ErrorResponse {"message":"File type \"image/x\" is not supported...","code":422}
```

Для не-HTTP контекстов (queue/console/Temporal/relay) per-request локали нет: те же
исключения там либо не доходят до интерсептора (queue), либо переводятся в статической
локали translator — допустимо, перевод не происходит «слишком рано» в бизнес-коде.

## Контракты реализации

### Данные и БД

`Не затрагивается` — миграций, таблиц и колонок задача не меняет.

### API и внешние контракты

Структура ответа ошибки не меняется: остаётся `{"message": "...", "code": <int>}` /
`{"message":"...","code":422,"errors":[...]}`. Меняется только **язык** строки `message`
и то, что middleware теперь читает входной заголовок `Accept-Language` (стандартный
HTTP-заголовок; при отсутствии — поведение по default). Новых маршрутов нет.

Новый внутренний контракт (не публичный HTTP API):

```php
// packages/spiral-api-errors/src/Exception/TranslatableException.php
namespace GianTiaga\SpiralApiErrors\Exception;

interface TranslatableException
{
    public function translationKey(): string;

    /** @return array<string, string> */
    public function translationParameters(): array;
}
```

## Фазы выполнения

### 1. Пакет `spiral-api-errors`: контракт `TranslatableException` + перевод на границе

Цель: дать пакету механизм перевода 4xx-исключений по ключу+параметрам в локали запроса,
сохранив прежнее поведение для не-translatable исключений.

Что сделать:
- Создать `packages/spiral-api-errors/src/Exception/TranslatableException.php` (интерфейс
  выше; `declare(strict_types=1)`, PHPDoc `@return array<string, string>`).
- В `ApiExceptionInterceptor::domainExceptionResponse` (`packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php`)
  guard-clause: если `$exception instanceof TranslatableException` — вернуть
  `errorResponse(message: $this->translator->trans(id: $exception->translationKey(),
  parameters: $exception->translationParameters()), status: $status)`; иначе прежний
  `errorResponse(message: $exception->getMessage(), status: $status)`. `TranslatorInterface`
  уже инжектится в конструктор.

Результат: пакет переводит translatable 4xx в текущей локали; обычные `\DomainException`
рендерятся как раньше. Приложение ещё компилируется и зелёное (его исключения интерфейс
пока не реализуют → ветка `getMessage()`).

Сценарии тестирования:
- Translatable-исключение (fixture, реализующий интерфейс) с ключом и параметром
  `{type}` → тело содержит переведённую строку с подставленным параметром.
- Translatable-исключение, ключ без перевода в `FakeTranslator` → возвращается сам ключ
  (документируем поведение псевдо-перевода без каталога).
- Не-translatable `\DomainException` с 4xx → тело = `getMessage()` (регресс прежнего
  поведения сохранён, существующие кейсы не падают).

Проверка:
- `composer -d packages/spiral-api-errors install` (при необходимости),
  `composer -d packages/spiral-api-errors test`, `composer -d packages/spiral-api-errors phpstan`.
- `PackagePortabilityTest` зелёный — новый `src/Exception/TranslatableException.php` чист от
  `App\`/framework-типов (интерфейс без зависимостей от приложения), пакет остаётся переносимым.
- `make test` (приложение) — остаётся зелёным.

### 2. Приложение: переводимые исключения, миграция ~23 точек выброса, каталоги локалей

Цель: доменные 4xx-исключения несут ключ+параметры; все точки выброса используют ключи;
каталоги ru/en заполнены; перевод виден в ответах.

Что сделать:
- `NotFoundException`, `ForbiddenException`, `ValidationException`, `AuthenticationException`
  (`app/src/Shared/Domain/Exception/*.php`): `implements TranslatableException`, конструктор
  `(string $translationKey, array $translationParameters = [])` с PHPDoc
  `@param array<string, string> $translationParameters`,
  `parent::__construct(message: $translationKey, code: self::STATUS_CODE)`, методы
  `translationKey()` и `translationParameters()`. `InvalidDomainValueException` (500) — без
  изменений.
- Мигрировать все 23 точки выброса на ключи. Паттерн:
  - 1 аргумент (только ключ) — позиционно: `throw new NotFoundException('app.media.not_found');`
  - 2 аргумента (ключ + параметры) — именованно (rules.md:17):
    `throw new ValidationException(translationKey: 'app.media.unsupported_file_type',
    translationParameters: ['type' => $value]);`
  - Представительные файлы: `app/src/Modules/Media/Application/Command/Media/{DeleteMedia,
    MakeMediaPermanent,CompleteMediaUpload,RequestMediaUpload,ProcessMedia,
    RecordMediaProcessingFailure}/*Handler.php`,
    `app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php`,
    `app/src/Modules/Media/Application/Service/MediaTypeResolver.php`,
    `app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php` (3 точки,
    сейчас именованный `message:` — переписать на `translationKey`).
  - Параметризованные сообщения (`sprintf '%s'`) переходят в `{name}`-плейсхолдеры:
    `MediaTypeResolver` → `{type}`; `RequestMediaUploadHandler:128` → `{mimeType}`.
  - Одинаковый русский текст в нескольких точках использует **один** ключ (без дублей-суффиксов).
- Заполнить `app/locale/ru/messages.php` и `app/locale/en/messages.php` всеми ключами
  (`app.media.*`, `app.system.*`), значения с `{name}`-плейсхолдерами. Формат файла —
  как у каталогов пакета (`return [ 'ключ' => 'строка', ];`, `declare(strict_types=1)`).
  Канонический набор уникальных ключей (23 точки → ниже; повторы схлопнуты):

  | Ключ | ru | en | Где |
  |---|---|---|---|
  | `app.media.not_found` | Медиа не найдено. | Media not found. | 6 хендлеров `Media` |
  | `app.media.access_denied` | Нет доступа к этому медиа. | You do not have access to this media. | `DeleteMedia`,`MakeMediaPermanent`,`CompleteMediaUpload` |
  | `app.media.not_ready` | Медиа ещё не готово. | Media is not ready yet. | `GetMediaUrl` |
  | `app.media.conversion_not_found` | Запрошенная конверсия отсутствует. | The requested conversion is missing. | `GetMediaUrl` |
  | `app.media.cannot_make_permanent` | Постоянным можно сделать только загруженное или готовое медиа. | Only uploaded or ready media can be made permanent. | `MakeMediaPermanent` |
  | `app.media.upload_not_pending` | Загрузка медиа не ожидает подтверждения. | Media upload is not awaiting confirmation. | `CompleteMediaUpload` |
  | `app.media.multipart_upload_not_found` | Для медиа не найдена multipart-загрузка. | No multipart upload was found for the media. | `CompleteMediaUpload` |
  | `app.media.uploaded_object_mismatch` | Загруженный объект отсутствует или его размер не совпадает с заявленным. | The uploaded object is missing or its size does not match the declared one. | `CompleteMediaUpload` |
  | `app.media.conversion_dimensions_out_of_range` | Ширина и высота конверсии вне допустимого диапазона. | Conversion width and height are out of the allowed range. | `CompleteMediaUpload` |
  | `app.media.file_size_exceeded` | Размер файла превышает допустимый предел. | File size exceeds the allowed limit. | `RequestMediaUpload` |
  | `app.media.file_name_without_extension` | Имя файла должно содержать расширение. | File name must contain an extension. | `RequestMediaUpload` |
  | `app.media.mime_not_allowed` | MIME-тип «{mimeType}» не разрешён спецификацией загрузки. | MIME type "{mimeType}" is not allowed by the upload specification. | `RequestMediaUpload` |
  | `app.media.unsupported_file_type` | Тип файла «{type}» не поддерживается для загрузки. | File type "{type}" is not supported for upload. | `MediaTypeResolver` |
  | `app.system.swagger_ui_disabled` | Swagger UI выключен. | Swagger UI is disabled. | `SwaggerController` (×2) |
  | `app.system.openapi_yaml_not_generated` | OpenAPI YAML ещё не сгенерирован. | OpenAPI YAML has not been generated yet. | `SwaggerController` |

  Финальные формулировки/ключи исполнитель может уточнить, сохранив принцип «один текст — один
  ключ» и плейсхолдеры `{type}`/`{mimeType}`. Дополнительно завести тестовый ключ
  `app.system.test_resource_not_found` (ru «Тестовый ресурс не найден.» / en «Test resource
  not found.») — для тестового fixture, чтобы не переиспользовать продакшен-ключи модулей.
- Обновить **юнит-тест исключений** `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php`:
  строки 18/26/34/42 конструируют `ValidationException`/`AuthenticationException`/
  `ForbiddenException`/`NotFoundException` именованным `message:` → переименовать в
  `translationKey:` (строка 50 `InvalidDomainValueException(message: ...)` остаётся без
  изменений). Добавить ассерты новых методов `translationKey()` и `translationParameters()`
  (включая дефолт `[]`) для 100%-покрытия новых веток интерфейса на app-классах.
- Обновить тестовый fixture `tests/App/Modules/System/Http/ApiErrorTestController.php`:
  `domain()` бросает `new NotFoundException('app.system.test_resource_not_found')` (выделенный
  тестовый ключ из каталогов, без переименованного `message:`).
- Обновить ассерты переведённых сообщений (на этой фазе детерминизм — явным `setLocale(...)`,
  LocaleMiddleware ещё нет):
  - `tests/Feature/Modules/System/Http/ApiErrorHttpTest.php`:
    `testApiRouteWithDomainExceptionReturnsJsonError` (строки 22-29, сейчас ждёт «Тестовый
    ресурс не найден.») — переведённое сообщение в заданной локали.
  - `tests/Feature/Modules/System/Http/OpenApiHttpTest.php` строки 54/64/74 (Swagger-ошибки) —
    переведённые сообщения ключей `app.system.openapi_yaml_not_generated`/
    `app.system.swagger_ui_disabled`.
- Обновить Application-feature/unit-тесты, ассертящие текст исключений
  (`tests/Feature/Modules/Media/Application/*HandlerTest.php`,
  `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php` и др.) — проверять ключ
  `translationKey()`/параметры или переведённое сообщение.

Результат: при `setLocale('en')` ответы ошибок на английском, при `setLocale('ru')` — на
русском; ключи интерполируют параметры.

Сценарии тестирования:
- Каждый мигрированный тип ошибки (NotFound/Forbidden/Validation) → корректный ключ → ответ
  на ожидаемом языке.
- Параметризованная ошибка (`MediaType`/MIME) → параметр подставлен в обоих языках.
- Существующие feature-тесты медиа зелёные с обновлёнными ассертами.

Проверка:
- Контроль полноты миграции:
  `grep -rn "throw new \(NotFoundException\|ValidationException\|ForbiddenException\|AuthenticationException\)" app/src | grep -v "/Exception/"`
  — каждое сырое русское сообщение заменено ключом `app.*`, ни одной русской строки в аргументе.
- `make test`, `make phpstan`.

### 3. Выбор языка: `LocaleConfig`, `app/config/locale.php`, `LocaleMiddleware`, регистрация

Цель: локаль запроса резолвится из `Accept-Language ∩ supported → default` и выставляется в
translator в начале запроса.

Что сделать:
- `app/config/locale.php`: `return ['supported' => ['ru', 'en'], 'default' => \env('LOCALE', 'ru')];`.
- `LocaleConfig` — `App\Shared\Infrastructure\Configuration\Locale\LocaleConfig`,
  `final readonly class implements TypedConfig`, `configName(): 'locale'`, конструктор
  `(array $supported, string $default)` с PHPDoc `@param list<string> $supported`; в
  конструкторе валидировать `default ∈ supported`, иначе `InvalidConfigValueException`
  (паттерн как в `MediaConfig`). Авто-регистрируется `ConfigBootloader`.
- `LocaleMiddleware` — `App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware`
  (существующая папка `Framework/Middleware`, рядом с `Framework/Bootloader`),
  `implements Psr\Http\Server\MiddlewareInterface` (как `RouteNotFoundMiddleware` пакета).
  Логика: `AcceptHeader::fromString($request->getHeaderLine('Accept-Language'))->getAll()`;
  для каждого item с `getQuality() > 0` — primary-subtag из `(string) $item->getValue()`
  (**null-safe**: `getValue(): ?string`; `strtolower`, отрезать регион по `-`), первый
  `∈ $localeConfig->supported` → `setLocale($match)`; если совпадений нет →
  `setLocale($localeConfig->default)`.
  **Зависимость от конкретного `Spiral\Translator\Translator`** (не `TranslatorInterface` —
  в нём нет `setLocale()`; `Translator` реализует `LocaleAwareInterface`), плюс `LocaleConfig`,
  `LoggerInterface`. Ранний возврат, без вложенности 2+, `match`/коллекц.-пайплайны где уместно.
- Зарегистрировать в `RoutesBootloader::globalMiddleware()`
  (`app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php`) на **index 1**:
  `[ErrorHandlerMiddleware, LocaleMiddleware, RouteNotFoundMiddleware, DumperMiddleware,
  JsonPayloadMiddleware, HttpCollector]`. Так локализуются и route-not-found 404.
- Поправить `app/config/translator.php`: заменить баг `'fallbackLocale' => \env('LOCALE', 'en')`
  на `'fallbackLocale' => \env('FALLBACK_LOCALE', 'ru')` (независимый fallback); оставить
  `'locale' => \env('LOCALE', 'ru')`. Существующий `TranslatorConfig` (поля `locale`/
  `fallbackLocale`) маппится по-прежнему; `SimpleConfigBindingTest` ассертит только
  `locale === 'en'` (с `.env` `LOCALE=en` остаётся зелёным), fallback не ассертит.
- Тесты-инфраструктура: добавить `'locale'` в ожидаемый sorted-список
  `ConfigShapeTest::testConfigFilesAreExpectedTypedConfigSections` **на индекс 3 — между
  `'database'` и `'mailer'`** (`assertSame` строго проверяет порядок:
  `…,database,locale,mailer,…`) (`tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php`);
  обновить
  `ApiErrorHttpTest::testRouteNotFoundMiddlewareIsRegisteredAfterErrorHandlerMiddleware`
  (ожидать `[0]=ErrorHandler`, `[1]=LocaleMiddleware`, `[2]=RouteNotFoundMiddleware` —
  добавить ассерт на `[2]`). **Все** HTTP-тесты в `ApiErrorHttpTest` и `OpenApiHttpTest`,
  ассертящие локализованное сообщение, перевести на явный `->withHeader('Accept-Language',
  'ru'|'en')` и убрать ручной `setLocale(...)` — middleware теперь governs локаль запроса и
  переопределит и ручной `setLocale('ru')` (строки 33/57/105), и ambient-локаль из `setUp()`.
  Это снимает зависимость детерминизма от `.env LOCALE` (иначе в среде без `LOCALE` default
  станет `ru` и тесты, ждущие английский без заголовка, покраснеют). Консольный
  `OpenApiGenerateCommandTest` (не HTTP) middleware не затрагивает — без изменений.
- `.env.sample`: добавить `FALLBACK_LOCALE=ru` (и пометить добавление в локальный `.env`).

Результат: запрос с `Accept-Language: en` → ответы на английском, `ru` → русском,
неизвестный/пустой → default; решение фиксируется в DEBUG-логе.

Сценарии тестирования:
- `LocaleConfigTest` (mirror `MediaConfigTest`): маппинг секции `locale` из контейнера и из
  стаба; валидация `default ∉ supported` → `InvalidConfigValueException`.
- `LocaleMiddlewareTest` (unit, детерминированный `LocaleConfig(['ru','en'], 'ru')`; дублёр
  для `Spiral\Translator\Translator`, фиксирующий аргумент `setLocale` — `createMock`/
  малый fake): `en` → en; `en-US,en;q=0.9` → en; `ru` → ru; `de` → default; пустой заголовок
  → default; `*` → default; quality-порядок (`ru;q=0.3,en;q=0.9` → en); `en;q=0,ru` → ru
  (q=0 игнорируется).
- Обновлённые HTTP-тесты ошибок зелёные с `Accept-Language`.

Проверка: `make test`, `make phpstan`.

### 4. Сквозной интеграционный тест локали + финальный гейт

Цель: подтвердить полный путь `Accept-Language → перевод ошибки на язык пользователя` через
реальную HTTP-цепочку (middleware → interceptor) и закрыть требование «каждый роут — под
интеграционным тестом».

Что сделать:
- Интеграционные HTTP-кейсы (в `ApiErrorHttpTest` или новом `LocaleHttpTest`):
  `GET /test/api/errors/domain` с `Accept-Language: en` → английское сообщение;
  с `Accept-Language: ru` → русское; с неизвестным (`de`) → перевод default-локали
  (ассертить против `getContainer()->get(LocaleConfig::class)->default`, не хардкодить).
- Расширить `ApiErrorTestController` + `ApiErrorTestRoutesBootloader` тестовым роутом,
  бросающим translatable-исключение **с параметром** (ключ `app.media.unsupported_file_type`
  с `{type}`), и проверить интерполяцию параметра в обоих языках по HTTP.
- Queue-контекст: в `tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php` подтвердить, что
  миграция не сломала очередь — выброс мигрированного `NotFoundException` из
  `RecordMediaProcessingFailureHandler`/`ProcessMediaHandler` несёт корректный `translationKey()`,
  а перевод в очереди не выполняется (per-request локали нет — ожидаемое поведение).
- Прогнать полный гейт и убедиться в 100% покрытии новых классов/веток.

Результат: сквозной перевод ошибок по `Accept-Language` подтверждён интеграционно.

Сценарии тестирования:
- en/ru/неизвестный язык → корректный язык тела ошибки на реальном роуте.
- Параметризованная ошибка по HTTP → параметр подставлен.

Проверка: `make test`, `make phpstan`, `composer -d packages/spiral-api-errors test` и
`composer -d packages/spiral-api-errors phpstan` — всё зелёное.

## Тесты

Стратегия (`after_each_phase`): каждая фаза завершается своими тестами и прогоном проверок —
фаза 1 покрывает пакет (translatable/не-translatable/параметры), фаза 2 — миграцию
исключений и каталоги через обновлённые feature/unit-тесты, фаза 3 — `LocaleConfig` и
`LocaleMiddleware` (unit) плюс перевод HTTP-тестов на заголовки, фаза 4 — сквозной
интеграционный тест локали и финальный гейт. Покрытие держим 100% (gate `make test`/
coverage), каждый затронутый роут — под интеграционным тестом.

## Логирование

Стратегия (`debug_precise`): единственная новая точка логирования — `LocaleMiddleware`,
который на каждом запросе пишет **DEBUG** с точным контекстом резолва локали
(camelCase-ключи): `acceptLanguage` (сырой заголовок), `resolvedLocale` (итоговая локаль),
`usedFallback` (булев — сработал ли default). Это нормальный flow → DEBUG, не WARN.
В интерсепторе логирование для 4xx **не добавляется** (rules.md:84, arch «API-ошибки»:
4xx не логируются); существующее ERROR-логирование 500 не меняется.

Осознанный компромисс «ключ-как-message»: после миграции `getMessage()` мигрированных
исключений возвращает **ключ перевода** (`app.media.not_found`), а не русский текст. В
не-HTTP путях, где исключение всё же логируется по `getMessage()` (например ERROR-лог в
`ProcessMediaJob` при провале обработки), в лог попадёт ключ, а не русская строка — формально
расходится с духом rules.md:7 («логи на русском»). Это принятый side-effect выбранного дизайна
(перевод только на HTTP-границе, бизнес-код локаль-агностичен); по ключу однозначно
восстанавливается смысл. Если для конкретного лога нужен русский текст — логировать поверх
ключа осознанно, не меняя контракт исключения.

## Документация и эксплуатация

- `.env.sample` и локальный `.env`: добавить `FALLBACK_LOCALE` (рекоменд. `ru`). Учесть, что
  текущий `LOCALE=en` делает эффективный default резолва `en` в dev/test — при необходимости
  иной базовой локали менять `LOCALE`.
- Зафиксировать в `docs/rules.md` правило-задел (из research): будущие Spiral Filter-ы
  отдают переводимые сообщения валидатора (translation-домен `spiral-packages/symfony-validator`),
  а не русские строки в `#[Assert\...]`. В рамках этой задачи Filter-ов с `#[Assert\...]` нет —
  только правило на будущее (вне кода данного плана; вносится отдельно через `eda-docs`).
- Релиз без миграций БД. После мерджа — прогнать `php app.php openapi:generate` не требуется
  (контракт ответа не изменился структурно), но язык описаний OpenAPI по-прежнему задаётся в
  момент генерации.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:**
  - **БЛОКЕР:** `LocaleMiddleware` инжектит конкретный `Spiral\Translator\Translator`, а не
    `TranslatorInterface` — в интерфейсе нет `setLocale()` (он в `LocaleAwareInterface`),
    PHPStan level max иначе падает. (sonnet, opus)
  - **БЛОКЕР:** В фазу 2 добавлен `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php`
    (строки 18/26/34/42 с `message:` → `translationKey:`) и покрытие новых методов
    `translationKey()`/`translationParameters()` для гейта 100%. (opus)
  - Таблица 15 уникальных ключей `app.*` (повторяющиеся русские сообщения схлопнуты в один
    ключ; «Медиа не найдено.» — 6 точек, «Нет доступа…» — 3) + выделенный тестовый ключ
    `app.system.test_resource_not_found`. (sonnet)
  - grep-контроль полноты миграции 23 точек; queue-контекст `ProcessMediaJobTest` в фазе 4;
    `PackagePortabilityTest` как критерий фазы 1; кейс `en;q=0` в `LocaleMiddlewareTest`.
- **~ Изменено:**
  - Null-safe приведение `(string) $item->getValue()` (тип `?string`) и фильтр `getQuality() > 0`
    в `LocaleMiddleware`. (opus)
  - `LocaleMiddleware` переехал из `Shared/Infrastructure/Http/Middleware` (предложение research)
    в существующую `Shared/Infrastructure/Framework/Middleware`; интерфейс уточнён до
    `Psr\Http\Server\MiddlewareInterface`. (opus)
  - `'locale'` в `ConfigShapeTest` пинится на индекс 3 (между `database` и `mailer`) для
    строгого `assertSame`. (opus)
  - Правка `translator.php` оформлена как явный diff бага `env('LOCALE','en')` →
    `env('FALLBACK_LOCALE','ru')`; сверена с `TranslatorConfig`/`SimpleConfigBindingTest`
    (ассертит только `locale==='en'`). (sonnet)
  - Явно перечислены ломающиеся ассерты `ApiErrorHttpTest:22-29` и `OpenApiHttpTest:54/64/74`.
- **− Убрано:** условная формулировка «при необходимости расширить» в фазе 4 — заменена на
  обязательный параметризованный тестовый роут.
- **Отклонено:**
  - Trailing comma в PHPDoc-строках `@param`/`@return` (haiku) — синтаксически неприменимо,
    PHPDoc-теги запятыми не разделяются.
  - Требование «pre-step проверки текущего `globalMiddleware()`» (haiku) — порядок уже
    зафиксирован фактом кода в «Контексте» (`ErrorHandlerMiddleware` на index 0), отдельный
    шаг избыточен.
  - Подтверждено как корректное и не требующее правок (opus): порядок `catch` в интерсепторе
    цел (`FilterValidationException` не `\DomainException`); формулировка named-args точна;
    единый домен `messages` для пакетных и app-ключей; `setLocale` не рискует
    `LocaleException` (значения всегда `∈ supported`/`default`).

## Прогресс выполнения
Журнал: `docs/executions/2026-06-11_22-56_mnogoyazychnye-oshibki-api.md`

- [x] Фаза 1: Пакет `spiral-api-errors` — контракт `TranslatableException` + перевод на границе
- [x] Фаза 2: Приложение — переводимые исключения, миграция 23 точек выброса, каталоги локалей
- [x] Фаза 3: Выбор языка — `LocaleConfig`, `app/config/locale.php`, `LocaleMiddleware`, регистрация
- [x] Фаза 4: Сквозной интеграционный тест локали + финальный гейт
