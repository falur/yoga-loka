---
title: EmptySuccessResponse и 204 No Content для командных эндпоинтов Auth
date: 2026-06-16 13:41
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
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
Сейчас два командных эндпоинта Auth возвращают бессмысленный ответ-заглушку:
`POST /api/v1/auth/code/request` → `200 {"data":{"status":"sent"}}`, `POST /api/v1/auth/logout` →
`200 {"data":{"status":"ok"}}`. Поле `status` всегда одно и то же, человеку не показывается,
клиенту ничего не сообщает (нарушение «строка-статус» из rules.md п.30 и «технический контракт» п.29).

Нужно: добавить в пакет `packages/spiral-openapi` базовый Response-класс `EmptySuccessResponse`,
который отдаёт `204 No Content` с пустым телом, научить генератор OpenAPI корректно отражать его в
схеме (код `204`, без `content`), перевести оба эндпоинта на него и удалить ресурсы-заглушки
`RequestCodeResultResource` и `LogoutResultResource`.

Готово, когда: оба эндпоинта возвращают `204` с пустым телом; в `public/openapi/openapi.yml` для них
стоит `responses.204` без `content` и нет `responses.200`; зелёные `make phpstan`, `make test` и
проверки пакета `spiral-openapi`.

## Контекст
Факты из кода (проверено):

- `AbstractJsonResponse::toResponse()` всегда `json_encode`-ит объект и ставит `Content-Type: application/json`
  (`packages/spiral-openapi/src/Response/AbstractJsonResponse.php`). Для пустого 204 он не подходит — нужен
  отдельный класс, реализующий `ConvertsToHttpResponse` напрямую.
- `HttpResponseInterceptor` обрабатывает любой объект, реализующий `ConvertsToHttpResponse`, вызывая
  `toResponse()` (`packages/spiral-openapi/src/Response/Interceptor/HttpResponseInterceptor.php`). Новый класс
  подхватится автоматически, регистрация не нужна.
- `HttpStatus::NoContent = 204` уже есть (`packages/spiral-openapi/src/Response/Enum/HttpStatus.php`).
- Генератор жёстко кладёт успешный ответ под ключ `200` и всегда добавляет `content`
  (`SpecBuilder::operation()` и `successResponse()`, `packages/spiral-openapi/src/Spec/SpecBuilder.php:90,100`).
  `successResponse()` бросает исключение, если у метода нет `@return Wrapper<T>`. Значит для класса без
  дженерика нужна отдельная ветка ДО этой проверки.
- Парсер прогоняет `NameResolver`, поэтому `MethodMetadata->returnType`
  (`packages/spiral-openapi/src/Parser/PhpAstParser.php:144`, метод `typeName()`) для сигнатуры
  `: EmptySuccessResponse` хранится как ПОЛНЫЙ FQCN `GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse`.
  `PhpDocReturnParser` разбирает только дженерик-обёртки `Wrapper<T>` и для метода без дженерика вернёт
  `genericReturnType = null` — поэтому `successResponse()` на нём бросил бы исключение, и ветка 204 обязана
  перехватить метод ДО вызова `successResponse()`. Детект — точным сравнением FQCN
  `returnType === emptyResponseClass` (надёжно: NameResolver даёт полное имя; исключает ложное срабатывание
  одноимённого класса из другого namespace). Тип возврата команд строго не-nullable и не-union.
- Текущий закоммиченный `public/openapi/openapi.yml` устарел: содержит только `/health` и НЕ содержит ни
  одного Auth-маршрута (модуль Auth ещё не попадал в регенерацию). Поэтому регенерация в фазе 4 добавит весь
  Auth-блок (`code/request`, `code/verify`, `register`, `refresh`, `logout`) и связанные схемы — это большой
  diff, а не «204 на двух путях». Ключи путей в спецификации идут без префикса `/api/v1` (например,
  `/auth/code/request`): `routePrefix` `/api/v1` отражается в `servers.url`, а не в ключах `paths`
  (подтверждено существующим `AuthOpenApiGenerationTest`, который ищет подстроку `/auth/code/request:`).
- Базовые обёртки ответов сконфигурированы через `ResponseWrapperMapping`
  (`packages/spiral-openapi/src/Config/ResponseWrapperMapping.php`), который приложение собирает в
  `OpenApiConfig::toGeneratorConfig()` (`app/src/Shared/Infrastructure/Configuration/OpenApi/OpenApiConfig.php:62`).
  arch.md прямо описывает это как точку расширения: «приложение задаёт mapping базовых response wrappers».
  `SpecBuilder` сопоставляет обёртки по короткому имени класса (`shortName()`).
- `SpecBuilder` уже имеет `DebugLogger` (`$this->logger`, конструктор `SpecBuilder.php:20`) и пишет
  `debug`-строки про найденные операции — туда же ляжет точное debug-сообщение про ветку 204.
- Описание успешного ответа берётся из ключа перевода `gian_tiaga.spiral_openapi.successful_response`
  (`packages/spiral-openapi/locale/ru|en/messages.php`) — переиспользуется для 204, новый ключ не нужен.
- Тесты приложения лежат в `./tests` (корень репозитория). HTTP-поведение эндпоинтов проверяет
  `tests/Feature/Modules/Auth/Http/AuthHttpTest.php`, генерацию OpenAPI для Auth —
  `tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php`.
- `Spiral\Testing\Http\TestResponse::assertNoContent(int $status = 204)` проверяет статус И пустое тело
  (`vendor/spiral/testing/src/Http/TestResponse.php:90`) — именно то, что нужно для проверки 204.
- Тесты генератора в пакете: `packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php` +
  fixture-контроллеры в `packages/spiral-openapi/tests/Fixtures/Endpoint/Api/V1/Controller/` (паттерн для
  нового кейса — как `ExportController` для файловых ответов).
- Контроллеры запускаются командой `php app.php openapi:generate` внутри контейнера `app-http`
  (Makefile: `APP_SERVICE ?= app-http`, прецедент `make migrate`). Готовый YAML — `public/openapi/openapi.yml`.

## Принятые решения
1. **Контракт ответа: `204 No Content` без тела** для `POST /api/v1/auth/code/request` и
   `POST /api/v1/auth/logout`. Источник подтверждения: ответ пользователя в текущем обсуждении
   («можно сделать типо EmptySuccessResponse, фронт нормально обработает пустой ответ»). Поле-заглушка `status`
   удаляется как нарушение rules.md п.29/30.
2. **Новый базовый Response-класс `EmptySuccessResponse`** в пакете
   (`GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse`), реализует `ConvertsToHttpResponse`, отдаёт
   PSR-7 `204` с пустым телом и без заголовка `Content-Type`. Это санкционированное расширение семейства
   базовых обёрток ответов (rules.md п.59 перечисляет обёртки для ответов с телом и не запрещает no-content;
   arch.md закрепляет mapping как точку расширения). Источник: ответ пользователя.
3. **Детект через `ResponseWrapperMapping`, а не хардкод FQCN в пакете**: в маппинг добавляется поле
   `emptyResponseClass`, приложение задаёт его в `OpenApiConfig`. Так пакет остаётся переносимым.
   Источник: `decision_mode: recommend_and_ask`, рекомендовано и не оспорено пользователем; согласуется с arch.md.
   `emptyResponseClass` делается ОБЯЗАТЕЛЬНЫМ конструкторным параметром (а не опциональным с пустым
   дефолтом): это ломающее изменение сигнатуры `ResponseWrapperMapping`, но пакет используется только этим
   монорепо, и все три инстанцирования (`OpenApiConfig` + два в `OpenApiGeneratorTest`) обновляются в рамках
   плана. Опциональный дефолт отвергнут, так как потребовал бы лишней ветки «фича выключена» в `SpecBuilder`.
   Детект операции 204 — точным сравнением полного FQCN `returnType === emptyResponseClass` (см. «Контекст»).
4. **Описание 204 переиспользует ключ `gian_tiaga.spiral_openapi.successful_response`** — без нового ключа
   локали. Источник: `decision_mode: autonomous` по несущественной детали, причина — минимальность изменений
   (rules.md «точечные изменения», «не плодить»).
5. **Удаление `RequestCodeResultResource` и `LogoutResultResource`** целиком: оба добавлены в этой же
   незакоммиченной ветке, это редизайн до коммита, а не удаление чужого кода. Источник: ответ пользователя.
6. **Summary-докблоки контроллеров сохраняются**: у методов `requestCode`/`logout` первая не-`@` строка
   докблока используется генератором как `description` операции (`PhpAstParser::summaryFromDocComment()` →
   `SpecBuilder::operation()`). Удаляется ТОЛЬКО строка `@return ...`, summary остаётся. Текст summary у
   `requestCode` («Ответ всегда 200 (наличие пользователя не раскрываем)») переписывается на «Ответ всегда
   204…», сохраняя смысл «наличие пользователя не раскрываем». Источник: `decision_mode: autonomous`,
   причина — иначе теряется описание операции и текст становится ложным.

Ожидаемый объём (plan_size: normal): 1 новый файл, ~6 правок (пакет: маппинг, SpecBuilder, 2 тестовых
call-site, README; приложение: OpenApiConfig, AuthController), 2 удаления, новый fixture-контроллер +
правки тестов генератора, правки `AuthHttpTest` и `AuthOpenApiGenerationTest`, регенерация
`public/openapi/openapi.yml`.

## Целевой алгоритм
1. Клиент шлёт `POST /api/v1/auth/code/request` (или `/api/v1/auth/logout`).
2. Контроллер `AuthController` отрабатывает как раньше (диспатчит команду; для logout — после
   middleware-аутентификации), но вместо `DataResponse(...Resource)` возвращает `new EmptySuccessResponse()`.
3. `HttpResponseInterceptor` видит `ConvertsToHttpResponse`, вызывает `toResponse()`.
4. `EmptySuccessResponse::toResponse()` возвращает PSR-7 ответ со статусом `204` и пустым телом
   (без `Content-Type`). Клиент получает `204` без тела.
5. При `php app.php openapi:generate` генератор сканирует контроллеры. В самом начале `SpecBuilder::operation()`
   проверяется точное равенство FQCN `methodMetadata->returnType === config->responseWrapperMapping->emptyResponseClass`.
   Если совпало — формируется `responses: { '204': { description }, default: {...} }` без блока `content`, при этом
   `successResponse()`/`fileResponse`/`genericReturnType` для метода не вычисляются; при `debug: true` пишется
   точная debug-строка про эту ветку. Для всех прочих методов поведение неизменно (`200` с телом).

## Контракты реализации

### Данные и БД
Не затрагивается.

### API и внешние контракты
Меняются два эндпоинта (auth/permissions без изменений):

```text
POST /api/v1/auth/code/request
  Было:  200  body: {"data":{"status":"sent"}}
  Стало: 204  без тела
  Ошибки (default, без изменений): 422 (валидация email), 429 (rate limit), прочее → ErrorResponse

POST /api/v1/auth/logout   (Bearer access-токен, как раньше)
  Было:  200  body: {"data":{"status":"ok"}}
  Стало: 204  без тела
  Ошибки (default, без изменений): 401 (нет/невалидный токен), прочее → ErrorResponse
```

В OpenAPI-схеме для обоих операций:

```text
responses:
  '204':
    description: Успешный ответ.        # ключ gian_tiaga.spiral_openapi.successful_response
  default:
    description: Ошибка API.
    content:
      application/json:
        schema: { $ref: '#/components/schemas/ErrorResponse' }
```

Прочие эндпоинты (`code/verify`, `register`, `refresh`) и их схемы не меняются.

## Фазы выполнения

### 1. Класс `EmptySuccessResponse` и расширение маппинга (пакет)
Цель: дать пакету готовый no-content ответ и поле конфигурации для его распознавания.

Что сделать:
- Создать `packages/spiral-openapi/src/Response/EmptySuccessResponse.php`: `final class`, реализует
  `GianTiaga\SpiralOpenApi\Response\ConvertsToHttpResponse`; метод `toResponse(): ResponseInterface`
  возвращает `new Nyholm\Psr7\Response(status: HttpStatus::NoContent->value)` — пустое тело, без
  `Content-Type`. Без свойств и без дженерика. Трейт `HasHttpResponseMetadata` намеренно НЕ подключается:
  204 неизменяем, `withStatus()/withHeader()` не нужны (в комментарии класса зафиксировать это, чтобы
  по аналогии с `AbstractJsonResponse` трейт не добавили).
- В `packages/spiral-openapi/src/Config/ResponseWrapperMapping.php` добавить пятым конструкторным
  параметром `public string $emptyResponseClass` (обязательный, в конец списка после `errorResponseClass`).
- Перед финализацией: `grep -rn "new ResponseWrapperMapping"` (вне `vendor`) — известные call-site:
  `app/src/Shared/Infrastructure/Configuration/OpenApi/OpenApiConfig.php:62` (правится в фазе 3) и
  `packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:58,76`. Тип-хинт
  `ResponseWrapperMapping` в `OpenApiGeneratorConfig.php` — это объявление параметра, а не инстанцирование,
  правки не требует.
- Сразу обновить два инстанцирования в `OpenApiGeneratorTest.php:58,76`, добавив именованный аргумент
  `emptyResponseClass: EmptySuccessResponse::class` — чтобы PHPStan пакета остался зелёным сразу после
  смены сигнатуры (оба вызова уже используют именованные аргументы).
- Обновить пример в `packages/spiral-openapi/README.md` (создание `ResponseWrapperMapping`), добавив
  `emptyResponseClass`, чтобы документация не разъезжалась с сигнатурой.

Результат: пакет содержит no-content ответ; `ResponseWrapperMapping` знает класс пустого ответа; все
инстанцирования маппинга внутри пакета учитывают новый параметр (app-call-site правится в фазе 3).

Сценарии тестирования (test_strategy: after_each_phase):
- `toResponse()` возвращает статус `204`.
- Тело ответа пустое (`getBody()->getContents() === ''`).
- В ответе нет заголовка `Content-Type` (`$response->hasHeader('Content-Type') === false`).

Проверка:
- Новый `packages/spiral-openapi/tests/Response/EmptySuccessResponseTest.php` проходит.
- `composer -d packages/spiral-openapi phpstan` зелёный (сигнатура `ResponseWrapperMapping` согласована
  во всех call-site внутри пакета).

### 2. Ветка 204 в генераторе OpenAPI (пакет)
Цель: генератор отражает `EmptySuccessResponse` как `204` без `content`.

Что сделать:
- В `SpecBuilder::operation()` (`packages/spiral-openapi/src/Spec/SpecBuilder.php:88`) сейчас `responses`
  собирается inline-литералом, где `$this->successResponse(...)` вычисляется немедленно. Переписать так:
  сначала отдельной переменной вычислить успешную запись `$successResponses` (массив с ОДНИМ ключом), затем
  собрать `'responses' => [...$successResponses, 'default' => $errorResponse]`. Логика выбора `$successResponses`
  в начале метода:
  - если `$methodMetadata->returnType !== null && $methodMetadata->returnType === $config->responseWrapperMapping->emptyResponseClass`
    (точное равенство полного FQCN) → `['204' => ['description' => $this->translator->trans('gian_tiaga.spiral_openapi.successful_response')]]`
    без `content`, БЕЗ вызова `successResponse()` (а значит без требования дженерика/без обращения к `fileResponse`);
  - иначе → `['200' => $this->successResponse(...)]` как сейчас.
  `requestBody` и `parameters` остаются нетронутыми, `default` остаётся вне ветки.
- Для `logging_strategy: debug_precise`: в ветке 204 (отдельной строкой, по образцу существующего
  `$this->logger->debug('Найдена OpenAPI-операция: ...')` на `SpecBuilder.php:42`, а не вплетая в него)
  вызвать `$this->logger->debug(sprintf('Операция %s::%s отдаёт 204 No Content (EmptySuccessResponse).', $classMetadata->className, $methodMetadata->name))`.
- Добавить ИЗОЛИРОВАННЫЙ fixture-контроллер в отдельном дереве, чтобы не трогать существующие тесты и счётчики:
  `packages/spiral-openapi/tests/Fixtures/Endpoint/NoContent/Api/V1/Controller/CommandController.php`,
  namespace `GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\NoContent\Api\V1\Controller`, `final class` с
  summary-докблоком (по образцу `ExportController`), `use ...\Response\EmptySuccessResponse;` (тип в сигнатуре
  — короткое имя, чтобы NameResolver резолвил его в FQCN), публичный метод
  `run(): EmptySuccessResponse` с `#[Route(route: '/api/v1/commands/run', name: 'api.v1.commands.run', methods: ['POST'], group: 'api')]`,
  возвращающий `new EmptySuccessResponse()`. Инъекций зависимостей нет.

Результат: метод, возвращающий `EmptySuccessResponse`, генерируется как операция с `responses.204` без
`content`; существующие тесты и их `operationCount` не затрагиваются (новый fixture — в отдельном дереве).

Сценарии тестирования (test_strategy: after_each_phase):
- Новый отдельный тест-метод в `OpenApiGeneratorTest` (по образцу `testFixtureProjectGeneratesOpenApi30NullableForms`):
  строит `OpenApiGeneratorConfig` с `sourcePaths: [.../Fixtures/Endpoint/NoContent/Api/V1]`,
  `apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\NoContent\Api\V1'` и
  `ResponseWrapperMapping(..., emptyResponseClass: EmptySuccessResponse::class)`, генерирует спецификацию.
- `operationCount` равен `1`.
- У операции `api_v1_commands_run` есть `responses.204`, его `description` равен описанию успешного ответа.
- У операции `api_v1_commands_run` НЕТ `responses.200` и НЕТ ключа `content` внутри `204`.
- В `components/schemas` НЕТ схемы `EmptySuccessResponse` (no-content ответ не регистрируется как ресурс).
- Метод-204 без `@return`-дженерика НЕ приводит к `OpenApiGenerationException` (негативная регрессия на
  случай, если ветку 204 уберут).

Проверка:
- `composer -d packages/spiral-openapi test` и `composer -d packages/spiral-openapi phpstan` зелёные.
- Существующие тесты генератора (`operationCount === 4`, `assertGeneratedSpec`) не изменялись и проходят.

### 3. Подключение в приложении и перевод эндпоинтов
Цель: оба командных эндпоинта Auth отдают 204, ресурсы-заглушки удалены.

Что сделать:
- В `app/src/Shared/Infrastructure/Configuration/OpenApi/OpenApiConfig.php:62` добавить в
  `new ResponseWrapperMapping(...)` параметр `emptyResponseClass: EmptySuccessResponse::class` (с
  соответствующим `use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;`).
- В `app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php`:
  - метод `requestCode(...)`: тип возврата сменить на `EmptySuccessResponse`, вернуть
    `new EmptySuccessResponse()`; убрать ТОЛЬКО строку `@return DataResponse<RequestCodeResultResource>`
    (по rules.md п.63 дженерик-PHPDoc не требуется без дженерика), но СОХРАНИТЬ summary-докблок и переписать
    его текст «Ответ всегда 200 (наличие пользователя не раскрываем).» → «Ответ всегда 204 (наличие
    пользователя не раскрываем).»; сохранить инъекцию `TranslatorInterface` (нужна для `requestLocale`).
  - метод `logout(...)`: тип возврата сменить на `EmptySuccessResponse`, вернуть `new EmptySuccessResponse()`;
    убрать ТОЛЬКО строку `@return DataResponse<LogoutResultResource>`, summary-докблок сохранить.
  - удалить импорты `RequestCodeResultResource` и `LogoutResultResource`, добавить импорт
    `EmptySuccessResponse`; импорт `DataResponse` оставить (используется в `verifyCode`, `register`, `refresh`).
- Удалить файлы
  `app/src/Modules/Auth/Presentation/Http/Resource/RequestCodeResultResource.php` и
  `app/src/Modules/Auth/Presentation/Http/Resource/LogoutResultResource.php`.

Результат: контроллер возвращает no-content ответы; summary-описания операций сохранены; в коде не осталось
ссылок на удалённые ресурсы.

Сценарии тестирования (test_strategy: after_each_phase):
- `tests/Feature/Modules/Auth/Http/AuthHttpTest.php`, тест запроса кода
  (`testRequestCodeReturnsSentAndStoresLoginCode`, строки ~54–57): вместо `assertOk()` +
  `assertBodyContains('"status":"sent"')` использовать `assertNoContent()`; проверка, что `LoginCode`
  записан, сохраняется. (`assertNoContent()` из Spiral Testing проверяет и статус 204, и пустое тело —
  дополнительных проверок тела не добавлять.)
- Тот же файл, `testLogoutRevokesSession` (строка ~224): первый logout — `assertNoContent()` вместо
  `assertOk()`; повторный logout по тому же токену по-прежнему `assertUnauthorized()` (других проверок тела
  в методе нет).
- Тот же файл, `testRateLimitReturns429AfterExceedingAttempts` (цикл по `/api/v1/auth/code/request`,
  строки ~247–256): заменить `->assertOk()` в цикле на `->assertNoContent()`; финальный `->assertStatus(429)`
  не трогать. Без этой правки `make test` будет красным.
- `tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php`: добавить ОТДЕЛЬНЫЙ новый тест-метод
  (по образцу `testVerifyResultResourceSchemaMarksNullableFields`, через `Yaml::parseFile`, не дописывая в
  строковый `testGeneratesAllAuthRoutes`). Ключи путей — без префикса `/api/v1`. Ассерты:
  `$spec['paths']['/auth/code/request']['post']['responses']` содержит `'204'` и НЕ содержит `'200'`;
  то же для `$spec['paths']['/auth/logout']['post']['responses']`; и позитивно — у соседнего
  `$spec['paths']['/auth/code/verify']['post']['responses']` `'200'` ПРИСУТСТВУЕТ (защита от того, что 204
  ошибочно применился ко всем эндпоинтам).

Проверка:
- Указанные тесты в `tests/Feature/Modules/Auth/...` проходят (через `make test`).

### 4. Регенерация спецификации и полный прогон проверок
Цель: зафиксировать актуальный `public/openapi/openapi.yml` и убедиться, что весь набор проверок зелёный.

Что сделать:
- Перегенерировать спецификацию: `php app.php openapi:generate` внутри контейнера `app-http`
  (например, `make shell CMD="php app.php openapi:generate"`); закоммитить обновлённый
  `public/openapi/openapi.yml`.
- Учесть, что текущий `public/openapi/openapi.yml` устарел (только `/health`): регенерация добавит ВЕСЬ
  Auth-блок — `/auth/code/request`, `/auth/code/verify`, `/auth/register`, `/auth/refresh`, `/auth/logout`
  и связанные схемы (`TokenPairResource`, `VerifyResultResource` и т.д.). Это большой ожидаемый diff, а не
  «204 на двух путях». При ревью коммита оценивать весь добавленный Auth-блок целиком.
- Прогнать полный гейт.

Результат: сгенерированный артефакт соответствует коду; все проверки зелёные.

Сценарии тестирования (test_strategy: after_each_phase):
- В обновлённом `public/openapi/openapi.yml` у `/auth/code/request` и `/auth/logout` присутствует `204`
  без `content` и нет `200`; у `/auth/code/verify`, `/auth/register`, `/auth/refresh` присутствует `200` с
  телом (контракты этих эндпоинтов не изменились).

Проверка:
- `make phpstan` — зелёный.
- `make test` — зелёный.
- `composer -d packages/spiral-openapi phpstan` и `composer -d packages/spiral-openapi test` — зелёные.

## Тесты
Стратегия: `after_each_phase` — после каждой фазы добавляются/обновляются тесты и прогоняются проверки этой
фазы. Новые тесты: unit на `EmptySuccessResponse::toResponse()` — статус 204, пустое тело, отсутствие
`Content-Type` (фаза 1); отдельный тест генератора на 204-операцию через ИЗОЛИРОВАННЫЙ fixture, с негативными
ассертами (нет `200`, нет `content` в `204`, нет схемы `EmptySuccessResponse`, нет `OpenApiGenerationException`)
(фаза 2). Обновляются: `AuthHttpTest` (три теста → `assertNoContent()`: запрос кода, logout, rate-limit-цикл),
`AuthOpenApiGenerationTest` (новый метод: 204 у двух командных путей, 200 у соседнего). Существующие тесты
генератора (`operationCount === 4`, `assertGeneratedSpec`) НЕ меняются — fixture вынесен в отдельное дерево.
Полный гейт (`make phpstan`, `make test`, проверки пакета) — в финальной фазе 4.

## Логирование
Стратегия: `debug_precise`. Новых рантайм-логов в приложении не добавляется: `EmptySuccessResponse::toResponse()`
детерминирован и не имеет ветвлений/ошибок, логировать нечего. Точное debug-логирование добавляется только в
генераторе (фаза 2): в ветке формирования 204 `SpecBuilder` через существующий `DebugLogger` пишет
строку вида «Операция {Class}::{method} отдаёт 204 No Content (EmptySuccessResponse).». Лог включается
существующим флагом `openapi.debug`, не шумит в обычном рантайме и согласован с уже имеющимися debug-строками
генератора. Содержимое debug-строк тестом не верифицируется — как и прочие debug-строки генератора (они не
покрыты ассертами по тексту); это осознанно и не считается пробелом покрытия.

## Документация и эксплуатация
- `public/openapi/openapi.yml` перегенерировать и закоммитить (фаза 4).
- `packages/spiral-openapi/README.md` обновить пример `ResponseWrapperMapping` (фаза 1).
- Изменений в env/секретах/инфраструктуре нет. На клиентах учесть смену контракта: 204 без тела вместо
  `200 {"data":{"status":...}}` на двух командных эндпоинтах (зафиксировано в разделе «API и внешние контракты»).

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** детект операции 204 — точным сравнением полного FQCN `returnType === emptyResponseClass`
  (а не по короткому имени): надёжнее, исключает ложное срабатывание одноимённого класса из другого namespace.
- **+ Добавлено:** пропущенная правка `testRateLimitReturns429AfterExceedingAttempts` в `AuthHttpTest`
  (цикл `assertOk()` по `code/request` → `assertNoContent()`) — без неё `make test` красный.
- **+ Добавлено:** сохранение summary-докблоков `requestCode`/`logout` (удаляется только строка `@return`),
  с правкой текста «всегда 200» → «всегда 204», иначе терялось бы `description` операции в OpenAPI.
- **+ Добавлено:** учёт устаревшего `public/openapi/openapi.yml` (сейчас только `/health`): регенерация
  добавит весь Auth-блок — это ожидаемый большой diff, фаза 4 переписана.
- **+ Добавлено:** негативные ассерты генератора — нет схемы `EmptySuccessResponse` в `components`, нет
  `OpenApiGenerationException` на методе без дженерика; позитивный ассерт — соседние Auth-эндпоинты сохраняют `200`.
- **+ Добавлено:** явный скелет рефактора inline-массива `responses` в `SpecBuilder::operation()` (вынести
  `$successResponses` в переменную до вычисления, `default` оставить вне ветки) и порядок ветки 204 — в самом
  начале, до `fileResponse`/`genericReturnType`.
- **~ Изменено:** тест генератора на 204 вынесен в ОТДЕЛЬНЫЙ тест-метод с изолированным fixture-деревом
  (`Fixtures/Endpoint/NoContent/Api/V1`) вместо правки общего `assertGeneratedSpec` и трёх ассертов
  `operationCount` 4→5 — менее хрупко, согласуется с паттерном «отдельный тест на отдельный кейс».
- **~ Изменено:** обновление call-site `OpenApiGeneratorTest.php:58,76` перенесено в фазу 1 (сразу за сменой
  сигнатуры `ResponseWrapperMapping`), чтобы PHPStan пакета оставался зелёным по фазам.
- **~ Изменено:** зафиксировано, что `EmptySuccessResponse` намеренно НЕ использует трейт
  `HasHttpResponseMetadata` (204 неизменяем), и проверка отсутствия `Content-Type` — на unit-уровне.
- **Отклонено:** делать `emptyResponseClass` опциональным с пустым дефолтом (opus) — добавляет ветку «фича
  выключена» в `SpecBuilder`; пакет внутренний для монорепо, все call-site правятся, ломающее изменение
  обосновано в «Принятых решениях».
- **Отклонено:** утверждение (sonnet), что вызов `ResponseWrapperMapping` на `OpenApiGeneratorTest.php:58`
  позиционный — он использует именованные аргументы, как и строка 76; обе строки и так в списке правок.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-16_14-21_empty-success-response-204.md`

- [x] Фаза 1: Класс `EmptySuccessResponse` и расширение маппинга (пакет) + unit-тест
- [x] Фаза 2: Ветка 204 в генераторе OpenAPI (пакет) + fixture и тест генератора
- [x] Фаза 3: Подключение в приложении, перевод эндпоинтов, удаление ресурсов-заглушек + правки Auth-тестов
- [x] Фаза 4: Регенерация `public/openapi/openapi.yml` и полный прогон проверок
