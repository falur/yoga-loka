---
title: Многоязычные ошибки, отдаваемые наружу через API
date: 2026-06-11 20:28
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Многоязычные ошибки, отдаваемые наружу через API

## Суть

Приложение становится многоязычным, поэтому ошибки, которые API отдаёт наружу,
должны приходить пользователю на его языке, а не на захардкоженном русском.

Сейчас (факты из кода):

| Что | Состояние | Источник |
|---|---|---|
| Доменные исключения 4xx (`NotFoundException`, `ForbiddenException`, `ValidationException`, `AuthenticationException`) | Принимают сырую строку-сообщение на русском, код зашит в конструкторе | `app/src/Shared/Domain/Exception/*.php` |
| Интерсептор API-ошибок | Для 4xx отдаёт `getMessage()` **без перевода** | `packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:39` |
| Сообщения пакета (`route_not_found`, `validation_error`, `internal_server_error`) | Уже переводятся через Spiral translator, есть каталоги `ru`/`en` | `packages/spiral-api-errors/locale/{ru,en}/messages.php` |
| Каталоги приложения `app/locale/` | Пусты — переводов приложения нет | `app/locale/` |
| Выбор языка запроса | Отсутствует. Локаль зафиксирована `env('LOCALE','ru')`, fallback читает ту же переменную (баг) | `app/config/translator.php:11-12` |
| Модуль User/Auth, JWT-middleware, `authUserId` | Не существуют | `app/src/Modules/` = `Media`, `Outbox`, `System` |
| Точки выброса 4xx, которые мигрируют | ~23 (11 `NotFound`, 9 `Validation`, 3 `Forbidden`; `Authentication` пока не выбрасывается) | `grep` по `app/src` |

Проблема разбивается на два независимых куска: **(A)** определить язык запроса и
**(B)** сделать сообщения исключений переводимыми, переводя их на границе.

## Решение

### Архитектура потока

```text
HTTP Request (Accept-Language: en)
  -> ErrorHandlerMiddleware
  -> LocaleMiddleware (НОВЫЙ, Shared/Infrastructure)
       translator->setLocale( Accept-Language ∩ whitelist  ?: default 'ru' )
  -> RouteNotFoundMiddleware ... JsonPayloadMiddleware
  -> Controller -> Filter -> CommandBus/QueryBus -> Handler
       throw new NotFoundException('app.media.not_found')   // только ключ, без перевода
  -> ApiExceptionInterceptor (граница)
       $exception instanceof TranslatableException
         ? translator->trans(key, params)   // переводит в локали, уже выставленной middleware
         : getMessage()
  -> ErrorResponse {"message":"Media not found.","code":404}
```

Перевод происходит **один раз, на самой внешней границе** (интерсептор), в локали,
которую middleware выставил в начале запроса. Бизнес-код (Domain/Application)
остаётся локаль-агностичным и называет только *что* произошло (ключ + параметры),
а не *как это звучит*.

### A. Выбор языка: `LocaleMiddleware`

Источник локали — заголовок `Accept-Language`, пересечённый с белым списком
поддерживаемых локалей; при промахе — default `ru`. На профиль пользователя
**не** завязываемся (его модуля ещё нет, и решение принято не плодить связь).

- Живёт в приложении: `App\Shared\Infrastructure\Http\Middleware\LocaleMiddleware`,
  регистрируется в `globalMiddleware()` сразу после `ErrorHandlerMiddleware`
  (`app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:33-40`).
  Это соответствует `arch.md:369-371`: «Выбор языка пользователя… остаётся задачей
  request-слоя приложения», пакет только читает текущий locale.
- Зависит от `Spiral\Translator\TranslatorInterface` (`setLocale()` есть —
  `…/Translator/src/Translator.php:86`) и от нового typed-config `LocaleConfig`
  (`supported: ['ru','en']`, `default: 'ru'`) в
  `Shared/Infrastructure/Configuration/Locale` поверх `app/config/locale.php`
  (по правилу «Typed config для каждого config-файла», `rules.md:50`).
- Whitelist обязателен: без него `setLocale('xx')` приведёт к локали без каталога →
  все ключи вернутся как есть (псевдо-перевод). Риск снимается пересечением с
  `supported` и fallback на `default`.
- Заголовок парсится готовым `Spiral\Http\Header\AcceptHeader`
  (`…/Http/src/Header/AcceptHeader.php`) — не руками.

Заметка по конфигу: текущий `fallbackLocale => env('LOCALE','en')` фактически
читает ту же переменную `LOCALE`, что и `locale`, поэтому fallback не независим.
Привести к `locale => env('LOCALE','ru')`, `fallbackLocale => env('FALLBACK_LOCALE','ru')`.

### B. Переводимые исключения: ключ + параметры, перевод в интерсепторе

**Контракт в пакете** (`spiral-api-errors` — владелец рендеринга ошибок):

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

**Доменные исключения 4xx реализуют его** (пример `NotFoundException`):

```php
use GianTiaga\SpiralApiErrors\Exception\TranslatableException;

final class NotFoundException extends \DomainException implements TranslatableException
{
    private const int STATUS_CODE = 404;

    /** @param array<string, string> $translationParameters */
    public function __construct(
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
    ) {
        parent::__construct(message: $translationKey, code: self::STATUS_CODE);
    }

    public function translationKey(): string { return $this->translationKey; }

    /** @return array<string, string> */
    public function translationParameters(): array { return $this->translationParameters; }
}
```

**Точки выброса** (то же по длине, что и сырой текст, без `trans()`):

```php
throw new NotFoundException('app.media.not_found');
throw new ValidationException('app.media.unsupported_file_type', ['type' => $value]);
```

**Интерсептор переводит на границе:**

```php
// ApiExceptionInterceptor::domainExceptionResponse
if (!$exception instanceof TranslatableException) {
    return $this->errorResponse(message: $exception->getMessage(), status: $status);
}
return $this->errorResponse(
    message: $this->translator->trans(
        id: $exception->translationKey(),
        parameters: $exception->translationParameters(),
    ),
    status: $status,
);
```

**Каталоги приложения** (Spiral интерполирует фигурными скобками `{...}` через
`Translator::interpolate`, `…/Translator/src/Translator.php:45-66` — это **не**
symfony-style `%name%`):

```php
// app/locale/ru/messages.php
'app.media.not_found'            => 'Медиа не найдено.',
'app.media.unsupported_file_type'=> 'Тип файла «{type}» не поддерживается для загрузки.',
// app/locale/en/messages.php
'app.media.not_found'            => 'Media not found.',
'app.media.unsupported_file_type'=> 'File type "{type}" is not supported for upload.',
```

`app/locale` уже подключён к translator (`directory('locale')` в конфиге);
каталоги приложения и каталоги пакета объединяются в общем домене `messages`.

### Почему так, а не иначе

| Развилка | Выбор | Почему отклонены альтернативы (с риском) |
|---|---|---|
| Где переводить | На границе (интерсептор) | `trans()` в момент `throw` — завязка Domain/Application на i18n-фреймворк (запрещено `arch.md` deps и духом «без глобалок»), и перевод *слишком рано*: то же исключение бросается из очередей/консоли/Temporal/relay, где per-request локали нет → молчаливый default; вдобавок при логировании в лог попадёт язык пользователя вместо русского (`rules.md:6,9`). |
| Как нести сообщение | Ключ + параметры через типизированный интерфейс | Pass-through (`getMessage()` всегда через `trans()`) неоднозначен: ключ vs сырой текст, тихий пробрасывает непереведённое; enum кодов на модуль — лишний вес и дублирование кросс-модульных ошибок (`NotFound`). Типизированный контракт соответствует культуре проекта (явные контракты, no magic). |
| Где интерфейс | В пакете `spiral-api-errors` | Пакет — владелец рендеринга, уже ловит `\DomainException` приложения; marker-интерфейс — та же связь, минимум кода. Чистый вариант (интерфейс в Domain + свой app-интерсептор) дублирует логику пакета без выигрыша. Компромисс: `Shared/Domain` берёт зависимость на интерфейс first-party пакета — осознанно, интерфейс без framework-типов. |
| Источник локали | `Accept-Language` ∩ whitelist → default | Профиль пользователя отклонён: модуля User/Auth нет, не плодим связь. При появлении Auth тир «профиль» добавляется как первый шаг резолва (через request-атрибут от auth-middleware) без переделки. |
| Языки | `ru` default+fallback, `en` | Совпадает с правилами (код/логи на русском) и текущими каталогами пакета. Структура расширяема под новые языки добавлением каталога. |

### Поле валидации (Spiral Filter) — задел, не блокер

HTTP-Filter-ов с `#[Assert\...]` в коде пока нет. Когда появятся, полевые
сообщения валидации тоже отдаются наружу через
`ApiValidationErrorsRenderer` (`packages/spiral-api-errors/src/Filter/…:24`) и
должны быть переводимыми: использовать переводимые сообщения
`spiral-packages/symfony-validator ^1.5` (translation-домен валидатора) вместо
русских строк в атрибутах. В рамках текущей задачи это не мигрируется —
фиксируется как правило для будущих фильтров.

## Ответы на вопросы

| Вопрос | Ответ пользователя |
|---|---|
| Как определять язык пользователя | Изначально «профиль → заголовок», затем уточнено: **`Accept-Language` + fallback, без завязки на пользователя**. |
| Механизм переводимых исключений | **Ключ + параметры, перевод на границе** (интерсептор). Альтернатива `trans()` в `throw` отклонена как завязка на не тот слой и перевод не вовремя. |
| Где интерфейс `TranslatableException` | **В пакете `spiral-api-errors`**. |
| Какие языки / default | **`ru` (default + fallback) + `en`**. |
| Просьба показать код варианта 1 | Показан в чате и в разделе «Решение»; подтверждён. |

## Итог

Подход к реализации:

1. Сделать доменные исключения 4xx переводимыми: интерфейс
   `TranslatableException` в пакете `spiral-api-errors`, исключения
   `NotFound/Forbidden/Validation/Authentication` несут ключ + параметры; `InvalidDomainValueException` (500) не трогаем (не отдаётся наружу).
2. Научить `ApiExceptionInterceptor` переводить `TranslatableException` через
   translator на границе; не-translatable исключения — как раньше.
3. Перевести ~23 точки выброса с русского текста на ключи; завести каталоги
   `app/locale/ru/messages.php` и `app/locale/en/messages.php` с этими ключами
   (плейсхолдеры `{name}`).
4. Добавить `LocaleMiddleware` (Shared/Infrastructure) с резолвом
   `Accept-Language ∩ whitelist → default 'ru'`, typed-config `LocaleConfig`
   (`app/config/locale.php`), зарегистрировать в `globalMiddleware()` сразу после
   `ErrorHandlerMiddleware`; поправить независимый `fallbackLocale` в конфиге.
5. Покрыть тестами: интеграционный тест на разные `Accept-Language` (en/ru/неизвестный),
   тест перевода ключа с параметром, тест fallback на default.
6. Зафиксировать правило: будущие Filter-ы используют переводимые сообщения
   валидатора, а не русские строки в `#[Assert\...]`.

Дальше — передать в `eda-plan` для пошагового плана реализации.
