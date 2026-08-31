---
title: OpenAPI tools package and Swagger UI
date: 2026-05-18 15:23
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

Создать переиспользуемый Composer-пакет `tools/openapi`, который строит OpenAPI YAML-спецификацию из типизированного HTTP-слоя Spiral-проекта: маршрутов, Filter DTO, Response DTO, Resource-классов, enum-ов, PHPDoc-дженериков и атрибута `#[OpenApi(id: 'health', description: '...')]`. Добавить Swagger UI для просмотра сгенерированной спецификации. Результат готов, когда пакет генерирует `openapi.yml`, Swagger UI читает этот файл локально, а сценарии генерации покрыты тестами.

## Контекст

Проект является API-first backend на PHP 8.5, Spiral Framework, RoadRunner и Cycle ORM. Архитектура уже фиксирует, что API-документация должна генерироваться автоматически из типизированного HTTP-слоя `Endpoint\Api\V1`, а Swagger используется как UI для просмотра.

В текущем коде каталог `app/src/Endpoint/Api` ещё не создан. Есть инфраструктурный `RoutesBootloader`, который подключает `AnnotatedRoutesBootloader`, но реальные API-контроллеры, Filter DTO, Response DTO и Resource-классы ещё не реализованы.

В проекте уже есть локальный Composer-пакет `tools/phpstan`. Новый пакет нужно сделать похожим по форме: собственный `composer.json`, `src/`, `tests/`, `phpunit.xml`, отдельные Composer scripts и подключение в корневой проект через path repository.

Правила проекта запрещают ассоциативные массивы в публичных контрактах API. HTTP request должен приходить через Spiral Filter, а HTTP response должен возвращаться типизированными классами `DataResponse<T>`, `PaginationResponse<T>` или `CollectionResponse<T>`. Ресурсы должны наследовать `AbstractResource`, а методы контроллеров должны иметь PHPDoc `@return` с дженериком.

Для разбора PHP-кода и атрибутов нужен AST-парсер. В `composer.lock` уже есть `nikic/php-parser` версии `v5.7.0`. Для чтения PHPDoc-дженериков нужен PHPDoc-парсер, а не регулярные выражения. Актуальная стабильная версия `phpstan/phpdoc-parser` по Packagist на 2026-05-18: `2.3.2`.

Для записи YAML в текущем lock-файле уже есть `symfony/yaml` версии `v8.0.10`. Для обхода файлов в lock-файле уже есть `symfony/finder` версии `v8.0.8`. Для локального Swagger UI актуальная стабильная версия `swagger-api/swagger-ui` по Packagist на 2026-05-18: `v5.32.6`. Пакеты `phpstan/phpdoc-parser` и `swagger-api/swagger-ui` ещё не установлены в проект и должны быть добавлены при исполнении плана.

`zircote/swagger-php` проверен как альтернатива. Актуальная стабильная версия на 2026-05-18: `6.1.2`. Он не берётся как основа, потому что задача требует выводить схему из существующих request/response контрактов и дженериков, а не переносить OpenAPI-схемы в отдельный набор ручных атрибутов.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `normal план`.
- Подход к зависимостям подтверждён пользователем: `tools/openapi` как свой пакет, `symfony/yaml` для записи YAML, `swagger-api/swagger-ui` для просмотра, без `zircote/swagger-php` как основного генератора.
- Новый пакет создаётся в `tools/openapi` и оформляется как переиспользуемая библиотека `yoga-loka/openapi-tools` с namespace `Tools\OpenApi\`.
- Пакет не зависит от конкретного приложения `App\`. Все пути, namespace API-слоя, версия API, public output path и Swagger route prefix передаются через конфиг пакета или CLI-аргументы.
- OpenAPI генерируется статически из PHP-кода через `nikic/php-parser:v5.7.0`, `phpstan/phpdoc-parser:2.3.2`, `symfony/finder:v8.0.8` и `symfony/yaml:v8.0.10`.
- Swagger UI подключается локально через `swagger-api/swagger-ui:v5.32.6`, без CDN-зависимости.
- Атрибут `#[OpenApi(id: 'health', description: '...')]` является необязательным расширением метаданных: он переопределяет автоматически вычисленные operationId и описание операции. Если атрибута нет, генератор строит документацию из маршрута, имени контроллера, имени метода, типов, Filter DTO, Response/Resource и PHPDoc.
- OpenAPI-атрибуты пакета размещаются в `Tools\OpenApi\Attribute`, а в приложении используются через Composer autoload пакета.
- Пакет создаёт не runtime response-классы, а универсальные описатели response wrappers: `DataResponse<T>`, `CollectionResponse<T>`, `PaginationResponse<T>` и `ErrorResponse`. В проекте YogaLoka единственный источник истины для runtime-формата находится в классах `App\Endpoint\Api\V1\Response`, а пакет получает этот формат через mapping config.
- Формат `PaginationResponse<T>` не зашивается заранее. Сначала создаётся runtime-класс проекта с типизированным DTO метаданных пагинации, затем OpenAPI mapping и тесты повторяют фактический формат этого класса.
- `AbstractResource` и базовые response wrappers приложения реализуют `JsonSerializable`, чтобы Spiral мог корректно отдавать JSON-ответы. `AbstractResource` сериализует public readonly свойства через базовый reflective-механизм, а конкретные Resource-классы не пишут ручной `jsonSerialize()`.
- Для фактического `ErrorResponse` добавляется `ApiExceptionInterceptor`, который конвертирует типизированные исключения в JSON. `spiral-packages/yii-error-handler-bridge` удаляется из корневых зависимостей и bootloader-ов, потому что правила проекта уже запрещают этот пакет.
- Источник маршрутов для первой версии генератора строго ограничен атрибутом `Spiral\Router\Annotation\Route(route, name, methods, group, middleware, priority)`. Генератор не читает runtime route metadata и не поддерживает абстрактные `Get/Post`-атрибуты.
- API route регистрируются с полным path в `#[Route(route: '/api/v1/...', ...)]` и group `api`. В `RoutesBootloader` добавляется `api` middleware group без Cookies, Session и CSRF.
- OpenAPI-спецификация сохраняется в `public/openapi/openapi.yml`.
- Swagger UI отдаётся приложением по `/api/docs`, а YAML-спецификация доступна по `/api/docs/openapi.yml`.
- Swagger UI routes регистрируются в `RoutesBootloader` явно и только когда типизированный config разрешает Swagger UI. Код routes не вызывает `env()` напрямую.
- Генерация запускается консольной командой приложения, которая только собирает конфиг и делегирует в сервис пакета. Бизнес-логики в консольной команде нет.
- `#[OpenApi(id: ..., description: ...)]` не обязателен для API-методов. Генератор не падает из-за отсутствия атрибута и использует fallback-правила: operationId строится из route name или пары controller method, описание берётся из PHPDoc summary метода, а при его отсутствии остаётся пустым.
- PHPStan-правило пакета проверяет только корректность уже указанного `#[OpenApi]`: непустой `id`, уникальность `id` и корректный формат. Отсутствие атрибута не считается ошибкой.
- Переносимость пакета проверяется автоматическим тестом: production-код `tools/openapi/src` не должен содержать ссылок на namespace `App\`; `App\` допустим только в fixtures и README-примерах.
- OpenAPI-версия: `3.1.0`. Swagger UI `v5.32.6` поддерживает OpenAPI `3.1.0`, а корректность результата проверяется snapshot-тестами структуры и валидацией обязательных секций спецификации.
- Debug-логирование включается в генераторе для основных шагов: старт сканирования, найденные контроллеры, найденные операции, построенные схемы, пропущенные элементы, запись файла и ошибки. Логи пишутся на русском и не содержат секретов, токенов и персональных данных.

## Целевой алгоритм

1. Разработчик запускает команду генерации OpenAPI.
2. Консольная команда приложения создаёт конфиг генератора: корневой путь проекта, каталог `app/src/Endpoint/Api/V1`, namespace `App\Endpoint\Api\V1`, public output path `public/openapi/openapi.yml`, OpenAPI title, version и route prefix `/api/v1`.
3. Команда передаёт конфиг в сервис `Tools\OpenApi`.
4. Генератор пишет debug-лог о старте операции и выбранных путях.
5. Сканер файлов находит PHP-файлы контроллеров, Filter DTO, Response DTO, Resource-классов и enum-ов внутри настроенного API namespace.
6. AST-парсер читает классы, методы, атрибуты, типы параметров, return type и PHPDoc.
7. Парсер маршрутов извлекает HTTP method, path, route name, group, middleware и priority только из `Spiral\Router\Annotation\Route`.
8. Парсер операции читает `#[OpenApi(id: ..., description: ...)]` на методе контроллера, если атрибут есть. Если атрибута нет, он вычисляет operationId из route name или имени controller method и берёт описание из PHPDoc summary метода.
9. Парсер request-контракта строит path-параметры из route pattern и аргументов метода контроллера, а query/body-поля из Filter DTO и атрибутов Spiral Filter.
10. Парсер response-контракта читает return type метода контроллера и PHPDoc `@return DataResponse<Resource>` или другой базовый response-дженерик.
11. Schema builder строит OpenAPI schemas из Resource DTO, Response DTO, Filter DTO и enum-ов с сохранением camelCase JSON-ключей.
12. Generic resolver раскрывает `DataResponse<T>`, `CollectionResponse<T>` и `PaginationResponse<T>` в конкретные OpenAPI response schemas.
13. Spec builder собирает единый OpenAPI-документ версии `3.1.0` с `info`, `paths`, `components.schemas`, `operationId`, `description`, request body, query/path parameters, response schemas и error responses.
14. Валидатор генератора проверяет уникальность operationId, отсутствие пустых response schemas, отсутствие неизвестных generic-типов и корректность указанного `#[OpenApi]`. Отсутствие `#[OpenApi]` и пустое описание не блокируют генерацию.
15. YAML writer записывает `public/openapi/openapi.yml` атомарно: сначала временный файл, затем rename.
16. Swagger UI route отдаёт статические assets Swagger UI, указывает UI читать `/api/docs/openapi.yml` и возвращает `404` с `ErrorResponse`, если YAML-файл ещё не сгенерирован.
17. Разработчик открывает `/api/docs` и видит актуальную спецификацию, созданную из кода.

## Фазы выполнения

### 1. Каркас переиспользуемого пакета и базовые контракты

Цель: создать отдельный пакет генератора без привязки к `App\`.

Что сделать:
- Создать `tools/openapi/composer.json` с package name `yoga-loka/openapi-tools`, namespace `Tools\OpenApi\`, scripts `test` и `phpstan`.
- Добавить зависимости пакета с конкретными версиями: `nikic/php-parser:v5.7.0`, `phpstan/phpdoc-parser:2.3.2`, `symfony/finder:v8.0.8`, `symfony/yaml:v8.0.10`.
- Добавить dev-зависимости пакета для тестов по текущему стилю `tools/phpstan`.
- Подключить `tools/openapi` в корневой `composer.json` через path repository и dev dependency.
- Создать структуру `tools/openapi/src`: `Attribute`, `Config`, `Scanner`, `Parser`, `Schema`, `Spec`, `Writer`, `Console`, `Exception`, `Logging`.
- Реализовать `Tools\OpenApi\Attribute\OpenApi` с обязательным `id` и необязательным `description`.
- Реализовать конфиг генератора: API source paths, API namespace, route prefix, output file, OpenAPI title, OpenAPI version, response generic mapping, default error response mapping.
- Реализовать типизированные DTO результата сканирования и ошибок генерации без ассоциативных массивов в публичных контрактах.
- Реализовать PHPStan-правило пакета, которое проверяет корректность указанного `#[OpenApi]`: непустой `id`, уникальность `id` внутри анализируемого набора файлов и допустимый формат `id`.
- Реализовать тест переносимости пакета: в `tools/openapi/src` запрещены ссылки на `App\`, а исключения разрешены только в `tools/openapi/tests/Fixtures` и README-примерах.
- Добавить debug-логи на русском для старта генерации, выбранного конфига, количества найденных файлов и ошибок конфигурации.

Результат: `tools/openapi` является самостоятельным Composer-пакетом, подключён корневым проектом и содержит базовые типы для дальнейшей генерации.

Сценарии тестирования:
- Composer autoload пакета работает из корневого проекта.
- Атрибут `OpenApi` создаётся с `id` и `description`.
- Конфиг генератора валидирует отсутствующие пути, пустой namespace и некорректный output path.
- Публичные DTO пакета не используют `array<string, mixed>` и голый `mixed`.
- PHPStan-правило не падает на API-методе без `#[OpenApi]`.
- PHPStan-правило падает на пустом, дублирующемся или некорректном `id` внутри указанного `#[OpenApi]`.
- Production-код пакета не содержит namespace `App\`.

Проверка:
- `composer validate`
- `composer install`
- `composer phpstan`
- `composer test`
- `composer -d tools/openapi test`
- `composer -d tools/openapi phpstan`

### 2. Статический разбор HTTP-слоя и построение схем

Цель: научить пакет извлекать операции и схемы из типизированного PHP-кода.

Что сделать:
- Реализовать file scanner на `symfony/finder`.
- Реализовать AST parser на `nikic/php-parser` для классов, методов, атрибутов, параметров, return type и promoted properties.
- Реализовать PHPDoc parser на `phpstan/phpdoc-parser` для чтения `@return DataResponse<T>`, `@return CollectionResponse<T>` и `@return PaginationResponse<T>`.
- Реализовать route parser только для `Spiral\Router\Annotation\Route(route, name, methods, group, middleware, priority)`.
- Реализовать request parser для route pattern `/<id>`, аргументов метода контроллера и Spiral Filter DTO.
- Поддержать реальные Filter-атрибуты Spiral: `Post`, `Query`, `Route`, `Path`, `Data`, `NestedFilter`, nullable-свойства, свойства со значением по умолчанию и обязательные uninitialized-свойства.
- Реализовать response parser для базовых Response-классов и Resource-классов.
- Реализовать schema builder для scalar types, readonly DTO, public properties, enum-ов, lists, nested DTO, nullable types и generic wrappers.
- Реализовать resolver имён типов для `use`-алиасов, коротких имён, FQCN, namespace collisions и nested generic types в PHPDoc.
- Реализовать naming policy: JSON-ключи остаются camelCase, имена схем строятся из короткого имени класса без namespace collision.
- Реализовать error handling генератора через типизированные исключения: неизвестный generic, неразобранный route, отсутствующий response PHPDoc, конфликт operationId, конфликт имени schema.
- Добавить debug-логи на русском для каждого найденного контроллера, операции, request schema, response schema, enum schema и пропуска файла вне API namespace.

Результат: пакет строит внутреннюю модель OpenAPI-операций и компонентов из fixture-кода без запуска приложения.

Сценарии тестирования:
- Контроллер с `#[OpenApi(id: 'health', description: '...')]` превращается в операцию с operationId `health` и описанием.
- Контроллер без `#[OpenApi]` тоже попадает в OpenAPI: operationId строится из route name или controller method, описание берётся из PHPDoc summary или остаётся пустым.
- Filter DTO с query/body/path полями превращается в параметры и requestBody.
- Route pattern `/<id>` и аргумент метода `string $id` превращаются в path parameter.
- `DataResponse<HealthResource>` раскрывается в response schema с вложенным `data`.
- `CollectionResponse<UserResource>` раскрывается в массив ресурсов.
- `PaginationResponse<UserResource>` раскрывается согласно фактическому runtime-классу `PaginationResponse` проекта.
- Enum превращается в OpenAPI schema с допустимыми значениями.
- Вложенный DTO превращается в вложенную schema.
- `use`-алиас и короткое имя класса в PHPDoc резолвятся в правильный FQCN.
- Nested generic в PHPDoc резолвится без регулярных выражений.
- Конфликт двух одинаковых `id` приводит к понятному исключению.
- Метод контроллера без PHPDoc `@return` для generic response приводит к понятному исключению.

Проверка:
- `composer -d tools/openapi test`
- `composer -d tools/openapi phpstan`
- `composer phpstan`

### 3. Генерация YAML и интеграция в Spiral-приложение

Цель: добавить команду генерации и сохранить спецификацию в `public/openapi/openapi.yml`.

Что сделать:
- Реализовать spec builder для OpenAPI `3.1.0`: `openapi`, `info`, `servers`, `paths`, `components.schemas`.
- Реализовать YAML writer на `symfony/yaml` с атомарной записью файла.
- Добавить в приложение тонкую консольную команду генерации OpenAPI, которая создаёт конфиг и вызывает сервис пакета.
- Зарегистрировать консольную команду в текущей Spiral-структуре `Endpoint\Console` через штатный command bootloader/discovery, чтобы `php app.php openapi:generate` работал в runtime.
- Добавить config-файл приложения для OpenAPI, где env читается только в `app/config/*.php`.
- Настроить package mapping для response wrappers YogaLoka: `DataResponse<T>`, `CollectionResponse<T>`, `PaginationResponse<T>`, `ErrorResponse`.
- Создать минимальные базовые API-контракты в `Endpoint\Api\V1`, которые нужны генератору и соответствуют правилам: `AbstractResource`, response wrappers, `ErrorResponse`.
- Реализовать runtime-сериализацию: `AbstractResource` и все response wrappers реализуют `JsonSerializable`, а конкретные Resource-классы не пишут ручной `jsonSerialize()`.
- Реализовать `ApiExceptionInterceptor` для JSON-ошибок и удалить `spiral-packages/yii-error-handler-bridge` из `composer.json`, `composer.lock` и `Kernel`.
- Добавить в `RoutesBootloader` middleware group `api` без Cookies, Session и CSRF; оставить JSON payload middleware глобальным.
- Добавить минимальный health endpoint в `Endpoint\Api\V1\Controller` как fixture реального приложения для проверки генерации.
- Зарегистрировать health endpoint через `#[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]`.
- Добавить `#[OpenApi(id: 'health', description: 'Проверка работоспособности API')]` на health endpoint как пример ручного уточнения метаданных.
- Добавить Composer script `openapi:generate`.
- Добавить debug-логи на русском для старта команды, успешной записи файла, количества операций, количества schemas и ошибок генерации.

Результат: корневой проект генерирует `public/openapi/openapi.yml` командой Composer или `php app.php`.

Сценарии тестирования:
- Команда генерации создаёт YAML-файл в `public/openapi/openapi.yml`.
- YAML содержит `openapi: 3.1.0`.
- YAML содержит path health endpoint.
- YAML содержит operationId `health`.
- YAML содержит описание из `#[OpenApi]`.
- YAML генерируется для endpoint-а без `#[OpenApi]` и содержит fallback operationId.
- YAML содержит response schema, полученную через `DataResponse<HealthResource>`.
- Ошибка генерации возвращает ненулевой exit code и понятный текст на русском.
- `GET /api/v1/health` отдаёт JSON через runtime response wrappers.
- Исключения API отдаются через `ApiExceptionInterceptor` в формате `ErrorResponse`.

Проверка:
- `composer openapi:generate`
- `test -f public/openapi/openapi.yml`
- `composer test`
- `composer phpstan`
- `composer -d tools/openapi test`
- `composer -d tools/openapi phpstan`

### 4. Swagger UI для чтения спецификации

Цель: добавить локальную страницу просмотра OpenAPI без внешнего CDN.

Что сделать:
- Добавить dependency `swagger-api/swagger-ui:v5.32.6` в корневой Composer.
- Настроить команду публикации Swagger UI assets из `vendor/swagger-api/swagger-ui/dist` в `public/swagger-ui`.
- Добавить Composer script `openapi:publish-assets`, который выполняет публикацию assets повторяемо.
- Добавить config-флаг включения Swagger UI в `app/config/openapi.php`; код регистрации маршрутов читает типизированный config и не вызывает `env()` напрямую.
- Зарегистрировать route `/api/docs` для HTML-страницы Swagger UI в `RoutesBootloader`.
- Зарегистрировать route `/api/docs/openapi.yml` для отдачи `public/openapi/openapi.yml` в `RoutesBootloader`.
- Настроить Swagger UI на чтение `/api/docs/openapi.yml`.
- Отдавать YAML с content type `application/yaml; charset=utf-8`.
- Если `public/openapi/openapi.yml` отсутствует, route `/api/docs/openapi.yml` возвращает `404` в формате `ErrorResponse`.
- Ограничить Swagger UI dev-режимом через конфиг приложения, чтобы production-доступ управлялся явно.
- Добавить debug-лог на русском при отдаче Swagger UI и YAML-файла.

Результат: локальный Swagger UI открывает сгенерированную спецификацию из проекта.

Сценарии тестирования:
- `/api/docs` отдаёт HTML Swagger UI.
- HTML содержит ссылку на локальные assets, а не CDN.
- `/api/docs/openapi.yml` отдаёт YAML-файл с content type для YAML.
- `/api/docs/openapi.yml` возвращает `404` и `ErrorResponse`, если файл ещё не сгенерирован.
- Swagger UI настроен на чтение `/api/docs/openapi.yml`.
- При выключенном Swagger UI route не регистрируется.
- Локальные assets отдаются из `public/swagger-ui`, HTML не содержит CDN-ссылок.

Проверка:
- `composer openapi:generate`
- `composer openapi:publish-assets`
- `phpunit` с интеграционными тестами routes `/api/docs` и `/api/docs/openapi.yml`
- `composer test`
- `composer phpstan`
- `composer -d tools/openapi phpstan`

### 5. Переиспользование, документация и контроль качества

Цель: закрепить пакет как переносимый инструмент для другого Spiral-проекта.

Что сделать:
- Добавить `tools/openapi/README.md` с установкой через path repository, примером конфига, примером `#[OpenApi]`, правилами response generic mapping и командой генерации.
- Обновить `docs/arch.md`: генератор OpenAPI живёт в `tools/openapi`, приложение только конфигурирует и вызывает пакет, Swagger UI читает YAML.
- Обновить `docs/rules.md`: `#[OpenApi(id: ..., description: ...)]` используется для ручного уточнения operationId и описания, но не обязателен; generic `@return` обязателен для базовых response wrappers; OpenAPI YAML генерируется командой перед релизом.
- Добавить корневые Composer scripts для пакета: `openapi:generate`, `openapi:test`, `openapi:phpstan`.
- Добавить интеграционные тесты реального проекта для health endpoint, `/api/docs`, `/api/docs/openapi.yml`, выключенного Swagger UI, content type YAML и локальных assets без CDN.
- Добавить unit-тесты пакета на fixture Spiral-проект, расположенный внутри `tools/openapi/tests/Fixtures`.
- Добавить автоматический тест, что пакет не содержит ссылок на `App\` вне fixture и конфигурационного примера.
- Добавить snapshot-тест структуры `openapi.yml`: `openapi`, `info`, `paths`, `components.schemas`, `operationId`, response schemas.
- Проверить, что исключения и логи на русском языке.

Результат: пакет можно перенести в другой Spiral-проект, подключить через Composer и настроить без копирования кода из YogaLoka.

Сценарии тестирования:
- Fixture-проект внутри пакета генерирует OpenAPI YAML.
- Корневой проект генерирует OpenAPI YAML из реального health endpoint.
- Swagger UI routes покрыты интеграционными тестами.
- Каждый новый route, включая health и Swagger routes, покрыт интеграционным тестом.
- PHPStan проходит для корня и `tools/openapi`.
- README пакета содержит полный минимальный пример подключения.

Проверка:
- `composer openapi:test`
- `composer openapi:phpstan`
- `composer test`
- `composer phpstan`
- `composer openapi:generate`
- ручная проверка `/api/docs` в dev runtime после запуска приложения

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы добавляются или обновляются тесты для изменённого поведения и сразу запускаются проверки этой фазы. Для пакета основа покрытия находится в `tools/openapi/tests`: unit-тесты парсеров, schema builder, generic resolver, YAML writer и fixture-проект. Для корневого приложения добавляются интеграционные тесты health endpoint, команды генерации и Swagger routes.

Ключевые сценарии: разбор `#[OpenApi]`, fallback-генерация без `#[OpenApi]`, Spiral route attributes, Filter DTO, enum-ов, Resource DTO, `DataResponse<T>`, `CollectionResponse<T>`, `PaginationResponse<T>`, ошибки конфликтов operationId и отсутствие generic PHPDoc.

Интеграционными тестами покрываются все новые routes: `GET /api/v1/health`, `GET /api/docs`, `GET /api/docs/openapi.yml`, выключенный Swagger UI, отсутствие YAML-файла, content type YAML и локальные Swagger assets без CDN.

Финальная проверка всего плана: `composer test`, `composer phpstan`, `composer -d tools/openapi test`, `composer -d tools/openapi phpstan`, `composer openapi:generate`, `composer openapi:publish-assets`.

## Логирование

Стратегия: `debug_precise`.

Генератор пишет debug-логи на русском на каждом важном шаге: старт команды, выбранный конфиг, количество найденных файлов, найденные контроллеры, найденные операции, построенные request/response schemas, раскрытые generic-типы, запись YAML, отдача Swagger UI, пропуски файлов и ошибки. В логах используются имена классов, route names, operationId и пути внутри проекта. Секреты, токены, заголовки авторизации, cookies и реальные пользовательские payload не логируются.

## Документация и эксплуатация

Обновить документацию пакета в `tools/openapi/README.md`, архитектуру в `docs/arch.md` и правила проекта в `docs/rules.md`.

Перед релизом запускать `composer openapi:generate` и проверять, что `public/openapi/openapi.yml` обновлён. Swagger UI остаётся dev-инструментом и включается через конфиг. Production-доступ к `/api/docs` открывается только явным значением конфигурации.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** точный источник маршрутов `Spiral\Router\Annotation\Route`, API middleware group без web middleware и явная регистрация Swagger routes в `RoutesBootloader`.
- **+ Добавлено:** runtime-сериализация `AbstractResource` и response wrappers через `JsonSerializable`, а также `ApiExceptionInterceptor` с удалением запрещённого `yii-error-handler-bridge`.
- **+ Добавлено:** автоматические проверки корректности указанного `#[OpenApi]`, отсутствия `App\` в production-коде пакета и интеграционного покрытия всех новых routes.
- **+ Добавлено:** обработка route path-параметров, реальных Spiral Filter-атрибутов, `use`-алиасов, FQCN, коротких имён и nested generic PHPDoc.
- **~ Изменено:** версия `swagger-api/swagger-ui` обновлена с `v5.32.5` на актуальную stable `v5.32.6` по Packagist.
- **~ Изменено:** `PaginationResponse<T>` больше не фиксируется заранее как `data/meta/cursor`; формат берётся из фактического runtime-класса проекта.
- **~ Изменено:** Swagger UI assets публикуются отдельной командой `openapi:publish-assets`, YAML отдаётся с `application/yaml; charset=utf-8`, отсутствие файла возвращает `ErrorResponse` с HTTP 404.
- **− Убрано:** расплывчатая поддержка route metadata и абстрактных `Get/Post` route attributes в первой версии генератора.
- **Отклонено:** удаление `GridBootloader` не включено в план, потому что Data Grid не участвует в OpenAPI-генераторе и не влияет на целевые API routes.

### После уточнения пользователя

- **~ Изменено:** `#[OpenApi]` больше не обязателен. При отсутствии атрибута генератор строит OpenAPI из доступных контрактов: route, controller method, PHPDoc, Filter DTO, Response/Resource и enum-ов.
- **~ Изменено:** PHPStan-правило проверяет только корректность уже указанного `#[OpenApi]`, а отсутствие атрибута не считается ошибкой.

## Прогресс выполнения
Журнал: `docs/executions/2026-05-18_15-57_openapi-tools-package.md`

- [x] Шаг 1: Каркас переиспользуемого пакета и базовые контракты
- [x] Шаг 2: Статический разбор HTTP-слоя и построение схем
- [x] Шаг 3: Генерация YAML и интеграция в Spiral-приложение
- [x] Шаг 4: Swagger UI для чтения спецификации
- [x] Шаг 5: Переиспользование, документация и контроль качества
