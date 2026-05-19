---
title: Пакет обработки API-ошибок
date: 2026-05-19 17:11
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: draft
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
  research: docs/researches/2026-05-19_16-59_api-error-handling-from-old-project.md
---

# План реализации

## Задача

Коротко: расширить `tools/openapi` ответом для ошибок валидации со списком найденных ошибок, вынести общую обработку API-ошибок в отдельный локальный Composer-пакет `tools/api-error`, подключить его в приложение и перевести `SwaggerController` на выброс нужного исключения вместо ручного возврата `ErrorResponse`.

Готовый результат: контроллеры и обработчики бросают типизированные исключения, цепочка выполнения controller/action превращает их в единый JSON `{"message":"...","code":...}` с правильным HTTP-статусом, ошибки Spiral Filter возвращаются как JSON `{"message":"Ошибка валидации","code":422,"errors":[...]}`, а Swagger routes больше не собирают ошибочный ответ вручную.

## Контекст

В новом проекте уже есть `tools/openapi` с response-классами для работы приложения: `Tools\OpenApi\Response\ErrorResponse`, `HtmlResponse`, `FileContentResponse` и перехватчиком `HttpResponseInterceptor`. Сейчас `SwaggerController` напрямую возвращает `ErrorResponse` при выключенном Swagger UI или отсутствующем YAML-файле.

Исследование подтвердило выбранный формат ошибки нового проекта: плоский JSON с полями `message` и `code`. Старый формат `{"error":{"code":...,"message":"..."}}` не переносится.

После мета-ревью пользователь уточнил контракт Filter-валидации: в ответе нужно отдавать не только общее сообщение и код 422, но и список ошибок, которые нашла валидация.

Правила проекта запрещают `try-catch` в контроллерах, Handler-ах и бизнес-логике. Исключения должны всплывать до API-границы, где их можно преобразовать в `ErrorResponse`.

Сейчас доменные HTTP-исключения в новом проекте ещё не заведены: нет `App\Domain\Exception\ValidationException`, `AuthenticationException`, `ForbiddenException` и `NotFoundException`. Старый проект имел такие классы в `App\Exception`, но переносить namespace один в один нельзя: новая архитектура ожидает доменные исключения внутри `Domain`.

Spiral `ValidationHandlerMiddleware` сейчас ловит ошибки Filter-валидации раньше цепочки выполнения controller/action и по умолчанию отдаёт `{"errors": ...}`. Для единого API-формата нужен свой renderer ошибок фильтров, но список найденных ошибок нужно сохранить.

`ApiExceptionInterceptor` сможет поймать только исключения, которые дошли до цепочки выполнения controller/action. Ошибки router, bootstrap и middleware, которые происходят раньше controller/action, остаются в зоне `ErrorHandlerMiddleware` и стандартного Spiral exception renderer. Этот план не заменяет глобальный обработчик Spiral.

Новые внешние библиотеки не нужны. План использует уже зафиксированные зависимости из `composer.lock`: `spiral/framework` 3.16.2, `psr/log` 3.0.2, `psr/http-message` 2.0, `nyholm/psr7` 1.8.2 и локальный пакет `yoga-loka/openapi-tools` `dev-main`.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1`.
- Обработку API-ошибок оформить отдельным локальным Composer-пакетом `tools/api-error`. Источник: ответ пользователя `A`.
- Новый пакет назвать `yoga-loka/api-error-tools`, namespace `Tools\ApiError`, каталог `tools/api-error`.
- Пакет `tools/api-error` зависит от `yoga-loka/openapi-tools`, потому что использует `Tools\OpenApi\Response\ErrorResponse`, `ValidationErrorResponse` и HTTP status enum из `tools/openapi`.
- В `tools/openapi` добавить отдельный response-класс для ошибок валидации: `ValidationErrorResponse` с полями `message`, `code`, `errors`.
- Элемент списка ошибок валидации описать типизированным DTO `ValidationErrorItemResponse` с полями `field` и `message`. Наружу отдавать список объектов, а не ассоциативный массив.
- В `tools/api-error/composer.json` добавить path repository на `../openapi`, чтобы пакет можно было устанавливать и проверять отдельно от корневого приложения.
- В корневом `composer.json` перенести `yoga-loka/openapi-tools:*` из `require-dev` в `require`. Это осознанный компромисс: response-классы и interceptor для работы приложения сейчас живут в пакете OpenAPI вместе с генераторной частью, поэтому весь пакет становится production-зависимостью.
- Доменные исключения приложения создать в `App\Domain\Exception`, а не в `tools/api-error`. Причина: переносимый tools-пакет не должен зависеть от namespace `App\`, а доменный слой приложения не должен зависеть от endpoint-инфраструктуры.
- Все новые доменные HTTP-исключения приложения должны явно расширять `\DomainException`, иначе `ApiExceptionInterceptor` их не поймает как доменные ошибки.
- Пакет ловит `\DomainException` с кодом, который есть в `Tools\OpenApi\Response\Enum\HttpStatus` и лежит в диапазоне 400-499, затем превращает этот код в HTTP-статус. Конкретные классы `ValidationException`, `AuthenticationException`, `ForbiddenException`, `NotFoundException` остаются в приложении и задают фиксированные коды 422, 401, 403, 404.
- Формат ошибки наружу остаётся текущим для нового проекта: `{"message":"...","code":422}`. Источник: ответ пользователя в research.
- `ErrorResponse` всегда создаётся с явным `code`; default `code = null` из `tools/openapi` не используется для API-ошибок.
- Для Filter-валидации используется не `ErrorResponse`, а `ValidationErrorResponse`, потому что этому сценарию нужен список ошибок.
- `SwaggerController` больше не возвращает `ErrorResponse` сам. При выключенном Swagger UI и при отсутствующем OpenAPI YAML он бросает `App\Domain\Exception\NotFoundException`.
- `ApiExceptionInterceptor` регистрируется в цепочке выполнения controller/action после `HttpResponseInterceptor`, чтобы он был ближе к controller/action, поймал исключение и вернул `ErrorResponse`, а `HttpResponseInterceptor` затем превратил DTO в PSR-7 HTTP response.
- Ошибки Spiral Filter приводятся к тому же формату через renderer из `tools/api-error`, потому что `ValidationHandlerMiddleware` работает на уровне HTTP middleware и не проходит через перехватчик controller/action.
- Привязка `Spiral\Filters\ErrorsRendererInterface` применяется глобально и затрагивает `api` и `web` middleware groups. Это допустимо для текущего API-first проекта: web-форм с HTML-валидацией нет, а единый JSON-формат ошибок важнее.

## Целевой алгоритм

1. HTTP-запрос попадает в обычные middleware Spiral.
2. Если ошибка происходит при разборе или валидации Filter, `ValidationHandlerMiddleware` ловит её и вызывает renderer из `tools/api-error`.
3. Renderer пишет debug-лог без пользовательских значений, превращает ошибки Spiral в список `ValidationErrorItemResponse`, создаёт `ValidationErrorResponse(message: 'Ошибка валидации', code: 422, errors: ...)`, ставит HTTP 422 и возвращает PSR-7 response.
4. Если запрос дошёл до controller/action, управление проходит через перехватчики controller/action.
5. `ApiExceptionInterceptor` вызывает следующий обработчик.
6. Контроллер, Handler или ValueObject при ошибке бросает типизированное доменное исключение.
7. `ApiExceptionInterceptor` ловит исключение на API-границе.
8. Для `\DomainException` с кодом, который есть в `HttpStatus` и лежит в диапазоне 400-499, перехватчик берёт код исключения как HTTP-статус и как `code` в JSON body.
9. Для остальных `\DomainException` перехватчик отдаёт HTTP 400 и warning-лог, потому что это доменная ошибка без корректного поддерживаемого HTTP-кода.
10. Для любого другого `\Throwable`, который дошёл до цепочки выполнения controller/action, перехватчик пишет error-лог и отдаёт HTTP 500 с сообщением `Внутренняя ошибка сервера`.
11. При обычных клиентских ошибках 401, 403, 404 и 422 перехватчик пишет debug-лог, потому что это нормальный пользовательский сценарий, а не сбой инфраструктуры.
12. Перехватчик возвращает `Tools\OpenApi\Response\ErrorResponse`.
13. Внешний `HttpResponseInterceptor` превращает `ErrorResponse` в PSR-7 HTTP response с JSON body и нужным статусом.
14. `SwaggerController` при недоступной документации бросает `NotFoundException`; общий механизм отдаёт тот же JSON 404, что и остальные API-ошибки.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Новые маршруты не добавляются.

Меняется единый контракт ошибок API:

| Сценарий | HTTP status | JSON body |
|---|---:|---|
| Ошибка валидации доменного значения | 422 | `{"message":"<сообщение>","code":422}` |
| Ошибка Filter-валидации Spiral | 422 | `{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"<сообщение>"}]}` |
| Ошибка аутентификации | 401 | `{"message":"<сообщение>","code":401}` |
| Запрещённый доступ | 403 | `{"message":"<сообщение>","code":403}` |
| Ресурс не найден | 404 | `{"message":"<сообщение>","code":404}` |
| Другая доменная ошибка с поддерживаемым кодом 400-499 | код исключения | `{"message":"<сообщение>","code":<код>}` |
| Доменная ошибка без корректного HTTP-кода | 400 | `{"message":"<сообщение>","code":400}` |
| Непредвиденная ошибка | 500 | `{"message":"Внутренняя ошибка сервера","code":500}` |

Для существующих Swagger routes сохраняется поведение по статусам:

| Метод и путь | Условие | Результат |
|---|---|---|
| `GET /api/docs` | Swagger UI выключен | HTTP 404 и JSON `{"message":"Swagger UI выключен.","code":404}` |
| `GET /api/docs/openapi.yml` | Swagger UI выключен | HTTP 404 и JSON `{"message":"Swagger UI выключен.","code":404}` |
| `GET /api/docs/openapi.yml` | YAML-файл не сгенерирован | HTTP 404 и JSON `{"message":"OpenAPI YAML ещё не сгенерирован.","code":404}` |

## Фазы выполнения

### 1. Расширить `tools/openapi` ответом для ошибок валидации

Цель: добавить в общий response-пакет типизированный JSON-ответ для валидации, чтобы `tools/api-error` не создавал свой отдельный формат.

Что сделать:
- Добавить `Tools\OpenApi\Response\ValidationErrorItemResponse` с публичными readonly-полями `field` и `message`.
- Добавить `Tools\OpenApi\Response\ValidationErrorResponse` с публичными readonly-полями `message`, `code`, `errors`.
- Тип `errors` сделать списком `ValidationErrorItemResponse`; в PHPDoc указать `@param list<ValidationErrorItemResponse> $errors`.
- Сделать `ValidationErrorResponse` наследником `AbstractJsonResponse`, чтобы он использовал общий механизм JSON и HTTP status/header из `tools/openapi`.
- Добавить тесты `tools/openapi/tests/Response/ValidationErrorResponseTest.php` на JSON body, HTTP 422 и `Content-Type: application/json; charset=utf-8`.
- Обновить `tools/openapi/README.md`: описать `ValidationErrorResponse` и пример тела ответа для Filter-валидации.

Результат: `tools/openapi` содержит типизированный ответ для ошибок валидации со списком найденных ошибок.

Сценарии тестирования:
- `ValidationErrorResponse` сериализуется в `{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"}]}`.
- Пустой список ошибок сериализуется как `errors: []`.
- Ответ выставляет HTTP 422 через `withStatus(HttpStatus::UnprocessableEntity)`.
- Обычный `ErrorResponse` остаётся без поля `errors`.

Проверка:
- `composer -d tools/openapi test -- --filter ValidationErrorResponseTest`
- `composer -d tools/openapi phpstan`

### 2. Создать переносимый пакет обработки API-ошибок

Цель: вынести общий код обработки API-ошибок из приложения в локальный пакет без зависимости от `App\`.

Что сделать:
- Создать `tools/api-error/composer.json` с именем пакета `yoga-loka/api-error-tools`, type `library`, namespace `Tools\ApiError\`, scripts `test` и `phpstan`.
- Добавить в `tools/api-error/composer.json` path repository на `../openapi`.
- Добавить зависимости пакета: `php >=8.5 <8.6`, `yoga-loka/openapi-tools:*`, `spiral/framework:^3.16`, `psr/log:^3.0`, `psr/http-message:^2.0`.
- Добавить dev-зависимости пакета: `phpunit/phpunit:^13.1`, `phpstan/phpstan:^2.1.54`.
- Добавить `tools/api-error/bootstrap.php`, `phpunit.xml`, `phpstan.neon` по образцу `tools/openapi`.
- Добавить path repository `tools/api-error` в корневой `composer.json`.
- Добавить `yoga-loka/api-error-tools:*` в корневой `require`, потому что это зависимость HTTP API во время работы приложения.
- Перенести `yoga-loka/openapi-tools:*` из корневого `require-dev` в корневой `require`, потому что приложение уже использует response-классы `tools/openapi` во время работы.
- Обновить `composer.lock` через Composer до запуска тестов нового пакета, чтобы root autoload уже видел `Tools\ApiError\`.
- Создать `Tools\ApiError\Interceptor\ApiExceptionInterceptor`.
- Создать `Tools\ApiError\Bootloader\ApiErrorBootloader`, который привязывает `Spiral\Filters\ErrorsRendererInterface` к `Tools\ApiError\Filter\ApiValidationErrorsRenderer`.
- В перехватчике ловить `\DomainException` и `\Throwable` только на API-границе.
- Для `\DomainException` с кодом, который есть в `HttpStatus` и лежит в диапазоне 400-499, создавать `ErrorResponse` с тем же кодом и `withStatus($status)`. Статус получать через `HttpStatus::tryFrom($code)`, чтобы неподдерживаемый код не вызвал новое исключение.
- Для `\DomainException` с неподдерживаемым или некорректным кодом создавать HTTP 400.
- Для неизвестных исключений внутри цепочки выполнения controller/action создавать HTTP 500 с сообщением `Внутренняя ошибка сервера`.
- Создать `Tools\ApiError\Filter\ApiValidationErrorsRenderer`, который реализует `Spiral\Filters\ErrorsRendererInterface` и возвращает `ValidationErrorResponse` с HTTP 422 как PSR-7 response.
- В `ApiValidationErrorsRenderer` преобразовать `array<string, string> $errors` из Spiral в `list<ValidationErrorItemResponse>`, где ключ становится `field`, а значение становится `message`.
- Добавить debug-логи на русском для клиентских ошибок и ошибок Filter-валидации. В контекст логов включать класс исключения, код, сообщение, имена полей с ошибками и не включать значения пользовательского ввода.
- Добавить warning-лог для доменной ошибки без корректного HTTP-кода.
- Добавить error-лог для непредвиденной ошибки с классом исключения, сообщением, файлом и строкой.
- Добавить `tools/api-error/tests/Interceptor/ApiExceptionInterceptorTest.php` на маппинг 401, 403, 404, 422, другой поддерживаемый 4xx, неподдерживаемый 4xx, некорректный код доменного исключения и 500.
- В каждом тесте `ApiExceptionInterceptorTest` проверять HTTP status, `Content-Type` и точный JSON body с `message` и `code`.
- Добавить unit-тест renderer-а Filter-ошибок на JSON body с `errors`, HTTP 422 и `Content-Type: application/json; charset=utf-8`.
- Добавить portability-тест: production-код `tools/api-error/src` не содержит ссылок на `App\`.

Результат: новый пакет самостоятельно тестируется и умеет превращать исключения и ошибки Filter-валидации в response-классы из `tools/openapi`.

Сценарии тестирования:
- Доменное исключение с кодом 422 превращается в HTTP 422 и body `{"message":"...","code":422}`.
- Доменное исключение с кодом 401 превращается в HTTP 401.
- Доменное исключение с кодом 403 превращается в HTTP 403.
- Доменное исключение с кодом 404 превращается в HTTP 404.
- Доменное исключение с кодом 409 превращается в HTTP 409.
- Доменное исключение с кодом 0 превращается в HTTP 400.
- Доменное исключение с кодом 499 превращается в HTTP 400, потому что такого значения нет в `HttpStatus`.
- Обычный `\RuntimeException` превращается в HTTP 500 без раскрытия внутреннего сообщения наружу.
- Ошибка Spiral Filter превращается в HTTP 422 с единым форматом API-ошибки и списком найденных ошибок.
- В production-коде пакета нет зависимости от `App\`.

Проверка:
- `composer validate --strict --no-interaction tools/api-error/composer.json`
- `composer update yoga-loka/api-error-tools yoga-loka/openapi-tools --with-dependencies --no-interaction`
- `composer -d tools/api-error test`
- `composer -d tools/api-error phpstan`

### 3. Подключить пакет в приложение и добавить доменные исключения

Цель: сделать новый пакет частью приложения во время работы и дать коду приложения типизированные исключения с фиксированными HTTP-кодами.

Что сделать:
- Зарегистрировать `Tools\ApiError\Bootloader\ApiErrorBootloader` в `Kernel::defineBootloaders()` до `RoutesBootloader`, чтобы `Spiral\Filters\ErrorsRendererInterface` указывал на `Tools\ApiError\Filter\ApiValidationErrorsRenderer`.
- Добавить `Tools\ApiError\Interceptor\ApiExceptionInterceptor` в `AppBootloader::INTERCEPTORS` после `HttpResponseInterceptor`.
- Создать `app/src/Domain/Exception/ValidationException.php`, `AuthenticationException.php`, `ForbiddenException.php`, `NotFoundException.php`.
- В каждом исключении явно расширить `\DomainException` и зашить код в конструкторе: 422, 401, 403, 404.
- Для `ValidationException` оставить простой контракт сообщения без сложных массивов в публичном API. Field errors не входят в текущий контракт, потому что публичный `ErrorResponse` содержит только `message` и `code`.
- Добавить `tests/Unit/Domain/Exception/ApiDomainExceptionTest.php` на базовый класс и коды новых исключений.
- Добавить `tests/App/Bootloader/ApiErrorTestRoutesBootloader.php`, который подключается только в `Tests\App\TestKernel` и регистрирует тестовые API routes для проверки обработки ошибок.
- Добавить тестовый controller и Filter внутри `tests/App/Endpoint/Api`, чтобы не добавлять служебные production routes ради тестов.
- Добавить `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php`, который проверяет обычный API route с брошенным `NotFoundException` через весь HTTP-путь и `ApiExceptionInterceptor`.
- В `ApiErrorHttpTest` проверить, что ошибка Filter-валидации тестового API route возвращает `{"message":"Ошибка валидации","code":422,"errors":[...]}` и HTTP 422.
- В `ApiErrorHttpTest` проверить, что `ValidationHandlerMiddleware` фактически использует `ApiValidationErrorsRenderer`, а не стандартный `JsonErrorsRenderer`.
- Добавить smoke-тест текущего поведения для ошибки вне цепочки выполнения controller/action, например неизвестного маршрута, чтобы зафиксировать границу этого плана.

Результат: приложение использует общий пакет для API-ошибок, а доменный код получает нужные типизированные исключения.

Сценарии тестирования:
- `composer install` видит оба локальных пакета: `yoga-loka/api-error-tools` и `yoga-loka/openapi-tools`.
- `AppBootloader` регистрирует `ApiExceptionInterceptor` после `HttpResponseInterceptor`.
- `ValidationException` имеет код 422.
- `AuthenticationException` имеет код 401.
- `ForbiddenException` имеет код 403.
- `NotFoundException` имеет код 404.
- API Filter-валидация больше не отдаёт голый `{"errors": ...}` без `message` и `code`.
- API Filter-валидация отдаёт список найденных ошибок в поле `errors`.
- Обычный API route с доменным исключением возвращает единый JSON body с `message` и `code`.
- Ошибка вне цепочки выполнения controller/action не считается покрытой `ApiExceptionInterceptor`.

Проверка:
- `composer validate --strict --no-interaction`
- `composer show yoga-loka/api-error-tools --no-interaction`
- `composer test -- --filter ApiDomainExceptionTest`
- `composer test -- --filter ApiErrorHttpTest`
- `composer test -- --filter OpenApiHttpTest`
- `composer phpstan`

### 4. Перевести SwaggerController на исключения

Цель: убрать ручную сборку ошибочного ответа из controller-а и проверить, что внешний HTTP-контракт не сломался.

Что сделать:
- В `SwaggerController::index()` заменить возврат `ErrorResponse` при выключенном Swagger UI на `throw new NotFoundException(message: 'Swagger UI выключен.')`.
- В `SwaggerController::spec()` заменить возврат `ErrorResponse` при выключенном Swagger UI на такой же `NotFoundException`.
- В `SwaggerController::spec()` заменить возврат `ErrorResponse` при отсутствующем YAML-файле на `throw new NotFoundException(message: 'OpenAPI YAML ещё не сгенерирован.')`.
- Убрать `ErrorResponse` из return type методов Swagger controller-а и из imports.
- Оставить успешные ответы без изменений: `/api/docs` возвращает `HtmlResponse`, `/api/docs/openapi.yml` возвращает `FileContentResponse`.
- Обновить существующие тесты `OpenApiHttpTest`, чтобы они проверяли не только HTTP 404, но и точный JSON body с `message` и `code`.
- Разделить disabled-сценарии Swagger: отдельно проверить `GET /api/docs` и отдельно `GET /api/docs/openapi.yml`, потому что сейчас существующий тест проверяет только первый маршрут.
- Добавить debug-логи только через общий `ApiExceptionInterceptor`; в controller-е логи не писать.

Результат: `SwaggerController` соответствует правилу тонкого controller-а и не строит ошибочные API-ответы вручную.

Сценарии тестирования:
- `GET /api/docs` при `openapi.swaggerEnabled=false` возвращает HTTP 404, JSON content type, `message: "Swagger UI выключен."`, `code: 404`.
- `GET /api/docs/openapi.yml` при `openapi.swaggerEnabled=false` возвращает тот же единый JSON 404.
- `GET /api/docs/openapi.yml` при отсутствующем файле возвращает HTTP 404, `message: "OpenAPI YAML ещё не сгенерирован."`, `code: 404`.
- `GET /api/docs` при включённом Swagger UI по-прежнему возвращает HTML.
- `GET /api/docs/openapi.yml` при существующем YAML по-прежнему возвращает YAML.

Проверка:
- `composer test -- --filter OpenApiHttpTest`
- `composer phpstan`
- `composer cs`

### 5. Финальная проверка и документация

Цель: убедиться, что пакет, приложение и документация согласованы.

Что сделать:
- Добавить в `tools/api-error/README.md` короткое описание подключения: пакет, порядок interceptors, привязка renderer-а Filter-ошибок, формат `ErrorResponse`.
- Обновить `tools/openapi/README.md`: в секции подключения заменить `require-dev` на `require`, описать `ValidationErrorResponse` и в примере порядка interceptors показать `HttpResponseInterceptor` перед `ApiExceptionInterceptor`.
- Обновить `docs/arch.md`: указать, что обработка API-ошибок живёт в `tools/api-error`, доменные исключения остаются в `App\Domain\Exception`, а `tools/api-error` зависит от `tools/openapi`.
- Обновить `docs/rules.md`: заменить ожидание локального `ApiExceptionInterceptor` в приложении на вариант из пакета `tools/api-error`.
- Запустить проверки нового пакета, `tools/openapi`, приложения и общий набор качества.
- Запустить production-проверку Composer: `composer install --no-dev --dry-run --no-interaction`, чтобы подтвердить доступность `tools/openapi` и `tools/api-error` без dev-зависимостей.
- При падении полного `composer test` из-за внешней инфраструктуры вроде MinIO зафиксировать это в отчёте выполнения, но не скрывать падение.

Результат: код, тесты и документация описывают один и тот же механизм API-ошибок.

Сценарии тестирования:
- README нового пакета показывает правильный порядок interceptors.
- Архитектура говорит, что обработка API-ошибок живёт в `tools/api-error`, а не в `app/src`.
- Правила всё ещё запрещают `try-catch` в контроллерах и Handler-ах.
- Полный набор проверок либо проходит, либо имеет явно описанную инфраструктурную причину падения.
- Production install без dev-зависимостей не теряет `Tools\OpenApi` и `Tools\ApiError`.

Проверка:
- `composer -d tools/api-error test`
- `composer -d tools/api-error phpstan`
- `composer -d tools/openapi test`
- `composer -d tools/openapi phpstan`
- `composer phpstan`
- `composer cs`
- `composer install --no-dev --dry-run --no-interaction`
- `composer test`

## Тесты

Стратегия: `after_each_phase`. После каждой фазы добавляются или обновляются тесты именно для изменённого поведения, затем запускаются точечные проверки этой фазы.

Основные уровни проверки:
- unit-тесты `tools/openapi` проверяют `ValidationErrorResponse`;
- unit-тесты `tools/api-error` проверяют маппинг исключений и renderer Filter-ошибок без запуска приложения;
- unit-тесты приложения проверяют коды доменных исключений;
- integration-тесты приложения проверяют HTTP-ответы Swagger routes, обычный API route с доменным исключением и Filter-валидацию;
- отдельная smoke-проверка фиксирует, что ошибки вне цепочки выполнения controller/action не обрабатываются `ApiExceptionInterceptor`;
- `composer phpstan` проверяет, что новые классы соблюдают строгие типы, именованные аргументы и правила контрактов;
- `composer cs` проверяет форматирование.

## Логирование

Стратегия: `debug_precise`.

Нужно добавить точные debug-логи на API-границе:
- 401, 403, 404 и 422 логируются на уровне debug как нормальные клиентские сценарии;
- Filter-валидация логируется на уровне debug с именами полей, но без значений пользовательского ввода;
- сообщения ошибок валидации уходят в HTTP-ответ клиенту, потому что это ожидаемый результат валидации, но в debug-лог не нужно писать значения пользовательского ввода;
- доменная ошибка без корректного HTTP-кода логируется на уровне warning;
- непредвиденный `Throwable` логируется на уровне error с классом исключения, сообщением, файлом и строкой;
- JSON-ответ клиенту от `ApiExceptionInterceptor` никогда не содержит stack trace, file, line, SQL, secret, token или внутреннее сообщение 500-ошибки.

## Документация и эксплуатация

Нужно обновить:
- `tools/api-error/README.md` с правилами подключения пакета;
- `docs/arch.md` с новой ролью `tools/api-error`;
- `docs/rules.md` с формулировкой про `ApiExceptionInterceptor` из пакета;
- `tools/openapi/README.md` с `ValidationErrorResponse` и примером порядка interceptors.

Для релиза важно:
- после Composer-изменений не править `composer.lock` вручную;
- проверить, что production install без dev-зависимостей не теряет response-классы `tools/openapi`, нужные во время работы;
- сохранить порядок interceptors: `HttpResponseInterceptor` перед `ApiExceptionInterceptor` в массиве, чтобы фактический вызов шёл через `ApiExceptionInterceptor` внутри `HttpResponseInterceptor`.
- понимать границу решения: ошибки router, bootstrap и middleware вне цепочки выполнения controller/action остаются на стандартном Spiral error handler и не становятся частью `ApiExceptionInterceptor`.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** отдельная проверка обычного API route с доменным исключением через весь HTTP-путь.
- **+ Добавлено:** тестовый route и Filter только для тестов, чтобы проверить Filter-валидацию без production-служебных маршрутов.
- **+ Добавлено:** production-проверка Composer через `composer install --no-dev --dry-run --no-interaction`.
- **+ Добавлено:** явное решение, что новые доменные исключения расширяют `\DomainException`.
- **+ Добавлено:** явная граница: `ApiExceptionInterceptor` не заменяет глобальный обработчик ошибок Spiral для router, bootstrap и middleware.
- **~ Изменено:** фаза Composer-подключения перенесена в начало, чтобы новый пакет был виден autoload до запуска его тестов.
- **~ Изменено:** документация `tools/openapi` теперь должна менять не только порядок interceptors, но и секцию подключения с `require-dev` на `require`.
- **~ Изменено:** тесты Swagger 404 должны проверять точный JSON body и оба disabled-маршрута.
- **Отклонено:** ограничивать renderer Filter-ошибок только `api` group. Для текущего API-first проекта глобальная привязка проще и осознанно затрагивает `web` group, где нет HTML-форм.

## Изменения после замечаний пользователя

- **+ Добавлено:** `tools/openapi` расширяется отдельным `ValidationErrorResponse` для ошибок валидации.
- **+ Добавлено:** Filter-валидация возвращает список найденных ошибок в поле `errors`.
- **~ Изменено:** обычный `ErrorResponse` остаётся без поля `errors`, чтобы не менять контракт всех остальных ошибок.
- **~ Изменено:** статус плана возвращён в `draft`, потому что после мета-ревью изменился внешний API-контракт.

## Прогресс выполнения
Журнал: `docs/executions/2026-05-19_17-29_api-error-handling-tools-package.md`

- [x] Шаг 1: Расширить `tools/openapi` ответом для ошибок валидации
- [x] Шаг 2: Создать переносимый пакет обработки API-ошибок
- [x] Шаг 3: Подключить пакет в приложение и добавить доменные исключения
- [x] Шаг 4: Перевести `SwaggerController` на исключения
- [x] Шаг 5: Финальная проверка и документация
