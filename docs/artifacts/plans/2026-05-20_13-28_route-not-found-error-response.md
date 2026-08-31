---
title: ErrorResponse для ненайденных маршрутов
date: 2026-05-20 13:28
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

Коротко: сделать так, чтобы любой ненайденный HTTP-маршрут возвращал нормальную API-ошибку `ErrorResponse`.

Готовый результат: запрос к пути, для которого нет маршрута, получает HTTP 404, заголовок `Content-Type: application/json; charset=utf-8` и тело `{"message":"Маршрут не найден.","code":404}`.

## Контекст

В проекте уже есть локальный пакет `tools/api-error`. Он обрабатывает API-ошибки, которые возникают внутри вызова controller/action, через `Tools\ApiError\Interceptor\ApiExceptionInterceptor`.

Ненайденный маршрут возникает раньше controller/action. Spiral router бросает `Spiral\Router\Exception\RouteNotFoundException`, а стандартный `Spiral\Http\Middleware\ErrorHandlerMiddleware` сейчас превращает это в свой ответ. Поэтому существующий `ApiExceptionInterceptor` не может поймать эту ошибку.

Текущий тест `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php` специально фиксирует старую границу: неизвестный route не проходит через `ApiExceptionInterceptor` и не возвращает единый `ErrorResponse`. Этот тест нужно заменить на новый ожидаемый контракт.

`Tools\OpenApi\Response\ErrorResponse` уже умеет превращаться в PSR-7 response через `toResponse()` и выставлять HTTP-статус через `withStatus(HttpStatus::NotFound)`.

Новые пакеты устанавливать не нужно. `psr/http-server-middleware` уже установлен в версии 1.0.2 через Spiral, но `tools/api-error` должен добавить прямую зависимость `psr/http-server-middleware:^1.0`, потому что новый код будет напрямую использовать `Psr\Http\Server\MiddlewareInterface` и `RequestHandlerInterface`.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `2`.
- Контракт ответа для любого ненайденного HTTP-маршрута: HTTP 404 и JSON `{"message":"Маршрут не найден.","code":404}`. Источник: ответ пользователя `1`.
- Обработку ненайденного маршрута добавить в `tools/api-error`, а не в приложение. Причина: это часть общей обработки API-ошибок и пакет уже зависит от `tools/openapi`.
- Реализовать отдельное HTTP middleware, которое ловит только `Spiral\Router\Exception\RouteNotFoundException`. Причина: это точный сценарий ненайденного маршрута; остальные ошибки router-а могут означать ошибку настройки и не должны маскироваться как обычный 404.
- Подключить middleware в глобальную HTTP-цепочку приложения сразу после `Spiral\Http\Middleware\ErrorHandlerMiddleware`. Причина: стандартный обработчик остаётся внешней защитой для остальных ошибок, а новое middleware успевает вернуть единый `ErrorResponse` до стандартного рендера 404.
- Применять новый JSON 404 ко всем HTTP-маршрутам приложения, а не только к путям `/api/...`. Источник: ответ пользователя `1` на вопрос про любой ненайденный HTTP-маршрут.
- Добавить прямую зависимость `psr/http-server-middleware:^1.0` в `tools/api-error/composer.json`. Источник версии: установленный пакет `psr/http-server-middleware` 1.0.2 из текущего `composer.lock`.
- `ApiExceptionInterceptor` не расширять для этого сценария. Причина: он работает внутри controller/action, а ненайденный маршрут возникает раньше.
- Логировать ненайденный маршрут на уровне `debug`: метод, путь без query-строки, HTTP-статус и класс исключения. Тело запроса, query-параметры и значения заголовков не логировать.

## Целевой алгоритм

1. HTTP-запрос входит в глобальную middleware-цепочку.
2. `ErrorHandlerMiddleware` остаётся первым и оборачивает все следующие middleware.
3. Новое middleware из `tools/api-error` вызывает следующий обработчик.
4. Если Spiral router находит маршрут, запрос идёт дальше без изменений.
5. Если Spiral router не находит маршрут, он бросает `RouteNotFoundException`.
6. Новое middleware ловит только это исключение.
7. Middleware пишет debug-лог на русском с методом, путём без query-строки, статусом 404 и классом исключения.
8. Middleware создаёт `ErrorResponse(message: 'Маршрут не найден.', code: 404)`.
9. Middleware выставляет HTTP-статус `HttpStatus::NotFound`.
10. Middleware возвращает PSR-7 response через `toResponse()`.
11. Клиент получает HTTP 404, `Content-Type: application/json; charset=utf-8` и тело `{"message":"Маршрут не найден.","code":404}`.
12. Остальные ошибки продолжают обрабатываться существующими механизмами: `ApiExceptionInterceptor`, renderer ошибок Filter или стандартный `ErrorHandlerMiddleware`.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Новые маршруты не добавляются.

Меняется ответ для HTTP-запроса, который не совпал ни с одним маршрутом приложения.

| Сценарий | HTTP status | JSON body |
|---|---:|---|
| Путь не совпал ни с одним маршрутом | 404 | `{"message":"Маршрут не найден.","code":404}` |
| Метод не совпал ни с одним маршрутом для этого пути | 404 | `{"message":"Маршрут не найден.","code":404}` |

Контракт доменных ошибок не меняется. Если контроллер или Handler бросает `App\Domain\Exception\NotFoundException`, ответ остаётся с сообщением из этого исключения.

## Фазы выполнения

### 1. Добавить middleware в `tools/api-error`

Цель: научить пакет возвращать единый `ErrorResponse` для ошибки router-а, не завися от приложения.

Что сделать:
- Создать `Tools\ApiError\Middleware\RouteNotFoundMiddleware`.
- Добавить в `tools/api-error/composer.json` прямую зависимость `psr/http-server-middleware:^1.0`.
- Реализовать `Psr\Http\Server\MiddlewareInterface`.
- В `process()` вызвать следующий обработчик.
- Поймать только `Spiral\Router\Exception\RouteNotFoundException`.
- В catch-блоке записать debug-лог на русском без query-параметров, тела запроса и пользовательских значений.
- Вернуть `ErrorResponse(message: 'Маршрут не найден.', code: 404)->withStatus(HttpStatus::NotFound)->toResponse()`.
- Добавить unit-тест в `tools/api-error/tests/Middleware/RouteNotFoundMiddlewareTest.php`.
- В тесте проверить HTTP 404, `Content-Type: application/json; charset=utf-8` и точное тело ответа.
- В тесте проверить, что middleware пропускает обычный успешный response без изменений.
- В тесте проверить, что middleware не ловит другой `\Throwable`.
- В тесте проверить debug-лог: он содержит `method`, `path`, `status`, `exceptionClass` и не содержит query-строку, тело запроса, cookie, authorization headers или другие значения из запроса.
- Проверить, что production-код `tools/api-error/src` по-прежнему не содержит ссылок на `App\`.

Результат: пакет `tools/api-error` умеет сам сформировать правильный 404 response для ненайденного маршрута.

Сценарии тестирования:
- `RouteNotFoundException` превращается в HTTP 404 и body `{"message":"Маршрут не найден.","code":404}`.
- Обычный response от следующего обработчика возвращается как есть.
- Любая другая ошибка не маскируется под 404.
- Debug-лог не раскрывает данные из query, тела запроса и заголовков.
- Пакет остаётся переносимым и не зависит от namespace приложения.

Проверка:
- `composer validate --strict --no-interaction tools/api-error/composer.json`
- `composer -d tools/api-error test -- --filter RouteNotFoundMiddlewareTest`
- `composer -d tools/api-error phpstan`

### 2. Подключить middleware в приложение

Цель: включить новый ответ для реальных HTTP-запросов приложения.

Что сделать:
- Добавить `Tools\ApiError\Middleware\RouteNotFoundMiddleware` в `RoutesBootloader::globalMiddleware()` сразу после `ErrorHandlerMiddleware::class`.
- Обновить `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php`: заменить старую проверку `testUnknownRouteStaysOutsideApiExceptionInterceptor()` на проверку нового точного ответа.
- Проверить в feature-тесте неизвестный путь, например `GET /test/api/errors/missing`.
- Проверить в feature-тесте неправильный метод для существующего пути, например `POST /test/api/errors/domain`.
- Добавить отдельную проверку, что доменный `NotFoundException` внутри существующего тестового API route всё ещё отдаёт своё сообщение `Тестовый ресурс не найден.`.
- Добавить reflection-тест порядка middleware: `ErrorHandlerMiddleware::class` должен быть первым, `RouteNotFoundMiddleware::class` должен идти сразу после него.
- Обновить `tools/api-error/README.md`: описать middleware для router 404 и порядок подключения после `ErrorHandlerMiddleware`.
- Обновить `docs/arch.md`: описать, что `tools/api-error` закрывает две границы ошибок API — controller/action через interceptor и router 404 через middleware.
- Не менять OpenAPI-генератор: ненайденный маршрут не является описываемым API route.

Результат: неизвестный HTTP-маршрут в приложении возвращает единый JSON 404, а существующие доменные 404 продолжают работать как раньше.

Сценарии тестирования:
- `GET /test/api/errors/missing` возвращает HTTP 404, `Content-Type: application/json; charset=utf-8` и `{"message":"Маршрут не найден.","code":404}`.
- `POST /test/api/errors/domain` возвращает HTTP 404, `Content-Type: application/json; charset=utf-8` и `{"message":"Маршрут не найден.","code":404}`.
- `GET /test/api/errors/domain` по-прежнему возвращает HTTP 404 и `{"message":"Тестовый ресурс не найден.","code":404}`.
- Ошибки Filter-валидации по-прежнему возвращают `ValidationErrorResponse` с полем `errors`.
- Порядок middleware сохраняет `ErrorHandlerMiddleware` первым и ставит `RouteNotFoundMiddleware` сразу после него.

Проверка:
- `composer test -- --filter ApiErrorHttpTest`
- `composer test -- --filter OpenApiHttpTest`
- `composer phpstan`
- `composer cs`

## Тесты

Стратегия: `after_each_phase`.

После первой фазы нужно запустить тесты пакета `tools/api-error`, потому что новая логика живёт именно там. После второй фазы нужно запустить интеграционный тест приложения, статический анализ и проверку стиля.

Финальная проверка перед завершением реализации:
- `composer -d tools/api-error test`
- `composer -d tools/api-error phpstan`
- `composer test -- --filter ApiErrorHttpTest`
- `composer test -- --filter OpenApiHttpTest`
- `composer test`
- `composer phpstan`
- `composer cs`

## Логирование

Стратегия: `debug_precise`.

Для ненайденного маршрута добавить только debug-лог. Это обычная клиентская ошибка, а не сбой приложения.

В лог можно писать:
- `method`;
- `path` без query-строки;
- `status`;
- `exceptionClass`.

В лог нельзя писать:
- тело запроса;
- query-параметры;
- cookie;
- authorization headers;
- произвольные значения из запроса.

## Документация и эксплуатация

Обновить `tools/api-error/README.md`: добавить middleware для 404 router errors и порядок подключения после `ErrorHandlerMiddleware`.

Обновить `docs/arch.md`: добавить короткое уточнение, что ошибки controller/action обрабатывает interceptor, а ненайденные маршруты обрабатывает HTTP middleware из `tools/api-error`.

Новые переменные окружения, миграции, команды и ручные действия при релизе не нужны.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** тест на неправильный HTTP-метод для существующего пути.
- **+ Добавлено:** reflection-тест точного порядка middleware.
- **+ Добавлено:** unit-тест debug-лога без query-строки, тела запроса и чувствительных заголовков.
- **+ Добавлено:** обязательное обновление `tools/api-error/README.md` и `docs/arch.md`.
- **+ Добавлено:** прямое требование `psr/http-server-middleware:^1.0` для пакета `tools/api-error`.
- **~ Изменено:** явно зафиксировано, что новый JSON 404 применяется ко всем HTTP-маршрутам приложения.
- **~ Изменено:** финальная проверка расширена до полного `composer test`.
- **Отклонено:** не добавлять корневой script `tools:api-error:qa`; текущий план уже запускает проверки пакета прямыми командами, а новый script будет отдельной задачей по уборке Composer scripts.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-20_13-38_route-not-found-error-response.md`

- [x] Шаг 1: Добавить middleware в `tools/api-error`
- [x] Шаг 2: Подключить middleware в приложение
- [x] Финальная проверка
