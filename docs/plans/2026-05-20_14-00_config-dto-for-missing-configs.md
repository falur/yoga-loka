---
title: DTO для всех недостающих конфигов
date: 2026-05-20 14:00
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

Создать типизированные DTO для всех конфигурационных файлов из `app/config/*.php`, для которых DTO ещё нет.

Готовый результат: каждый раздел конфигурации приложения можно получить из контейнера как отдельный typed config объект, существующие framework-конфиги Spiral продолжают работать как раньше, тесты подтверждают регистрацию и корректное преобразование конфигов.

## Контекст

В проекте уже есть инфраструктура для typed config:

| Факт | Где находится |
| --- | --- |
| `TypedConfig` требует метод `configName()` с именем раздела конфигурации. | `app/src/Infrastructure/Configuration/TypedConfig.php` |
| `ConfigMapper` берёт раздел из `ConfiguratorInterface` и преобразует его через Valinor. | `app/src/Infrastructure/Configuration/Mapping/ConfigMapper.php` |
| `ConfigBootloader` автоматически ищет `*Config.php` в `app/src/Infrastructure/Configuration`, регистрирует только классы с `TypedConfig` и пропускает вложенные DTO без этого интерфейса. | `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` |
| Сейчас DTO есть для `cache` и `openapi`. | `app/src/Infrastructure/Configuration/Cache`, `app/src/Infrastructure/Configuration/OpenApi` |
| Всего в `app/config` есть 11 файлов. DTO уже есть для 2 файлов, не покрыты 9 файлов. | `app/config` |

Конфиги, для которых DTO уже есть:

| Раздел | DTO |
| --- | --- |
| `cache` | `App\Infrastructure\Configuration\Cache\CacheConfig` |
| `openapi` | `App\Infrastructure\Configuration\OpenApi\OpenApiConfig` |

Конфиги, для которых нужно создать DTO:

| Раздел | Файл |
| --- | --- |
| `cycle` | `app/config/cycle.php` |
| `database` | `app/config/database.php` |
| `mailer` | `app/config/mailer.php` |
| `migration` | `app/config/migration.php` |
| `queue` | `app/config/queue.php` |
| `scaffolder` | `app/config/scaffolder.php` |
| `session` | `app/config/session.php` |
| `storage` | `app/config/storage.php` |
| `translator` | `app/config/translator.php` |

Правила проекта требуют DTO вместо неструктурированных массивов в публичных контрактах. Для конфигов остаются допустимы простые типизированные map-коллекции вида `array<string, SomeConfig>`, потому что сами Spiral config файлы являются границей framework-а и уже возвращают ассоциативные массивы.

Новые пакеты не нужны: `cuyz/valinor` уже установлен и используется текущим `ConfigMapper`.

Важное уточнение: `ConfigMapper` читает не только массив из файла, а итоговый результат `ConfiguratorInterface::getConfig()`. Spiral bootloader-ы добавляют default-поля поверх файлов. DTO должны описывать именно эту итоговую форму.

Итоговая форма, которую нужно покрыть:

| Раздел | Обязательные поля итогового `getConfig()` |
| --- | --- |
| `migration` | `directory`, `vendorDirectories`, `strategy`, `nameGenerator`, `table`, `safe` |
| `translator` | `locale`, `fallbackLocale`, `directory`, `directories`, `autoRegister`, `loaders`, `dumpers`, `domains` |
| `scaffolder` | `header`, `directory`, `namespace`, `declarations`, `defaults.declarations` |
| `cycle` | `schema.cache`, `schema.defaults`, `schema.collections`, `schema.generators`, `warmup`, `options`, `customRelations` |
| `database` | `logger.default`, `logger.drivers`, `default`, `aliases`, `databases`, `drivers` |
| `queue` | `default`, `connections`, `registry.handlers`, `registry.serializers`, `driverAliases`, `interceptors`, `aliases`, `pipelines`, `defaultSerializer` |
| `session` | `lifetime`, `cookie`, `secure`, `sameSite`, `handler` |
| `storage` | `default`, `servers`, `buckets` |
| `mailer` | `dsn`, `queue`, `from`, `queueConnection` |

## Принятые решения

- Покрыть DTO все 11 файлов из `app/config/*.php`; существующие `cache` и `openapi` не переписывать, а добавить только недостающие 9 корневых DTO и их вложенные DTO.
- Корневой DTO каждого раздела размещать в `App\Infrastructure\Configuration\<Section>\<Section>Config` и делать `implements TypedConfig`.
- Вложенные DTO размещать рядом с корневым DTO раздела. Они не реализуют `TypedConfig`, поэтому `ConfigBootloader` их найдёт как файлы, но не зарегистрирует как отдельные root config singleton.
- Имена `configName()` должны точно совпадать с именами файлов без `.php`: `cycle`, `database`, `mailer`, `migration`, `queue`, `scaffolder`, `session`, `storage`, `translator`.
- В каждом новом `configName()` использовать тот же способ подавления проверки magic scalar, что уже есть в `CacheConfig` и `OpenApiConfig`: `// @phpstan-ignore project.magicScalarLiteral`.
- Существующие `app/config/*.php` не менять в рамках этой задачи. DTO должны описывать текущую форму конфигов, а не проектировать новую.
- DTO должны описывать итоговый `ConfiguratorInterface::getConfig()` после default-ов Spiral bootloader-ов. Описывать только чистый файл `app/config/*.php` недостаточно.
- Framework-конфиги Spiral не заменять. Например, `Spiral\Cache\Config\CacheConfig`, `Spiral\Queue\Config\QueueConfig`, `Spiral\Storage\Config\StorageConfig` и другие должны продолжить получать исходный массив из `app/config`.
- Для значений, которые уже являются объектами vendor-библиотек в `app/config`, DTO должен принимать эти объекты по общему типу, когда он есть. Конкретные типы: драйверы базы как `Cycle\Database\Config\DriverConfig`, очередь pipeline connector как `Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface`, session handler как `Spiral\Core\Container\Autowire`.
- Для map-секций использовать точный PHPDoc, например `array<string, DatabaseConnectionConfig>`, `array<string, QueueConnectionConfig>`, `array<string, StorageServerConfig>`. Не использовать `mixed`.
- Открытые vendor-опции описывать DTO под текущую итоговую форму проекта, а не как `array<string, mixed>`. Для `storage.servers.s3.options` нужен отдельный DTO с `usePathStyleEndpoint: bool`.
- Для snake_case ключей внутри framework-конфигов подключить `CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase` в `ConfigBootloader::configMapper()`. Это нужно для `use_path_style_endpoint` и сохраняет camelCase в DTO.
- Перед DTO для конфигов с секретами изменить `ConfigMappingException`: не выводить `sourceValue()` в сообщение. В ошибке оставить путь, ожидаемый тип и фактический тип через `get_debug_type()`. Это решение принято по правилу проекта: секреты и персональные данные не должны попадать в диагностику.
- Тесты писать и запускать после каждой фазы, потому что выбранная стратегия `after_each_phase`.
- Новые runtime-логи не добавлять. Для стратегии `debug_precise` точная диагностика должна быть в тестах и в безопасном сообщении `ConfigMappingException`: имя раздела, имя DTO-класса, путь к ошибочному значению, ожидаемый тип и фактический тип без самого значения.

## Целевой алгоритм

1. Spiral загружает массивы из `app/config/*.php` как сейчас.
2. Spiral bootloader-ы добавляют свои default-поля в `ConfiguratorInterface`.
3. `ConfigBootloader` при старте приложения сканирует `app/src/Infrastructure/Configuration`.
4. Для каждого корневого класса, который реализует `TypedConfig`, `ConfigBootloader` регистрирует singleton в контейнере.
5. Когда код запрашивает конкретный typed config из контейнера, `ConfigMapper` берёт итоговый раздел по `configName()`.
6. Valinor преобразует итоговый массив или уже созданные vendor-объекты в readonly DTO.
7. Если структура раздела не совпадает с DTO, `ConfigMappingException` сообщает имя раздела, имя класса, путь, ожидаемый тип и фактический тип без сырого значения.
8. Framework bootloader-ы Spiral продолжают читать исходные config arrays и не зависят от новых DTO.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Не затрагивается.

## Фазы выполнения

### 1. Зафиксировать итоговую форму конфигов и безопасные ошибки

Цель: убрать риск неправильных DTO из-за default-ов Spiral и убрать риск вывода секретов в ошибках маппинга.

Что сделать:

- Добавить тестовый helper, который берёт разделы через реальный `ConfiguratorInterface::getConfig()` после boot-а тестового приложения и возвращает только ключи и типы значений, без вывода секретных значений.
- Зафиксировать в тесте список root-разделов, для которых должны быть DTO: все файлы `app/config/*.php`.
- Изменить `ConfigMappingException`, чтобы сообщение не включало `sourceValue()`.
- Добавить unit-тест на безопасную ошибку: если неверный тип пришёл в поле с секретом, сообщение содержит путь и тип, но не содержит само секретное значение.
- Подключить `ConvertKeysToCamelCase` в `ConfigBootloader::configMapper()`.
- Проверить поведение Valinor на итоговом `getConfig()` для уже существующих `CacheConfig` и `OpenApiConfig`, чтобы новый подход совпадал с текущей инфраструктурой.

Результат: исполнитель работает с фактической итоговой формой конфигов, а ошибки маппинга не раскрывают секреты.

Сценарии тестирования:

- Ошибка маппинга показывает раздел, DTO-класс, путь и типы.
- Ошибка маппинга не показывает DSN, S3 key, S3 secret, token и password.
- Список файлов `app/config/*.php` используется как источник ожидаемых root DTO.

Проверка:

- `composer test -- --filter Infrastructure\\\\Configuration`
- `composer phpstan`

### 2. Добавить DTO для простых и средних конфигов

Цель: покрыть разделы, где нет сложных открытых vendor-опций, и сразу проверить маппинг из реального `getConfig()`.

Что сделать:

- Создать `MigrationConfig` для `migration`: `directory: string`, `vendorDirectories: array<string, string>`, `strategy: string`, `nameGenerator: string`, `table: string`, `safe: bool`.
- Создать `MailerConfig` для `mailer`: `dsn: string`, `from: string`, `queueConnection: ?string`, `queue: ?string`.
- Создать `TranslatorConfig` для `translator`: `locale: string`, `fallbackLocale: string`, `directory: string`, `directories: array<int|string, string>`, `autoRegister: bool`, `loaders: array<string, class-string<LoaderInterface>>`, `dumpers: array<string, class-string<DumperInterface>>`, `domains: array<string, list<string>>`.
- Создать `SessionConfig` для `session`: `lifetime: int`, `cookie: string`, `secure: bool`, `sameSite: ?string`, `handler: ?Autowire`.
- Создать `ScaffolderConfig` для `scaffolder`.
- Для `scaffolder.declarations` создать DTO с `namespace: string`.
- Для `scaffolder.defaults.declarations` создать DTO с `namespace: string`, `postfix: string`, `class: ?class-string`, `options: ScaffolderDeclarationOptionsConfig`.
- Для `ScaffolderDeclarationOptionsConfig` описать текущие опции `directory: ?string` и `annotated: ?string`.
- Добавить unit-тесты на преобразование каждого раздела из ручного массива и из реального `ConfiguratorInterface::getConfig()`.
- Добавить negative unit-тест для каждого нового root DTO: неверный тип одного обязательного поля даёт читаемую безопасную ошибку.
- Добавить integration-тест, который получает эти DTO из контейнера и проверяет singleton.
- Проверить, что вложенные DTO не попадают в список singleton-ов `ConfigBootloader`.

Результат: `migration`, `mailer`, `translator`, `session` и `scaffolder` доступны как typed config singleton и совпадают с итоговым `getConfig()`.

Сценарии тестирования:

- `migration` сохраняет поля, добавленные Cycle migrations defaults.
- `mailer` сохраняет nullable `queueConnection` и nullable `queue`.
- `translator` сохраняет loaders, dumpers и domains.
- `session` сохраняет `sameSite: null` и `Autowire` handler.
- `scaffolder` сохраняет app declarations и vendor defaults declarations.
- Каждый новый root DTO имеет `configName()` с точным именем раздела.

Проверка:

- `composer test -- --filter Infrastructure\\\\Configuration`
- `composer phpstan`

### 3. Добавить DTO для сложных framework-конфигов

Цель: покрыть разделы с вложенными map-секциями, vendor-объектами и опциями без перехода на `mixed`.

Что сделать:

- Создать `DatabaseConfig` для `database`.
- Создать вложенные DTO для `database`: `DatabaseLoggerConfig`, `DatabaseConnectionConfig`.
- В `DatabaseConfig` описать `logger`, `default: string`, `aliases: array<string, string>`, `databases: array<string, DatabaseConnectionConfig>`, `drivers: array<string, DriverConfig>`.
- В `DatabaseLoggerConfig` описать `default: ?string`, `drivers: array<string, string>`.
- Создать `CycleConfig` для `cycle`.
- Создать вложенные DTO для `cycle`: `CycleSchemaConfig`, `CycleCollectionsConfig`, `CycleCustomRelationConfig`.
- В `CycleConfig` описать `schema`, `warmup: bool`, `options: ?Cycle\ORM\Options`, `customRelations: array<int|string, CycleCustomRelationConfig>`.
- В `CycleSchemaConfig` описать `cache: bool`, `defaults: array<string, class-string|object>`, `collections`, `generators: list<class-string>|array<string, list<class-string>>|null`.
- В `CycleCollectionsConfig` описать `default: string`, `factories: array<string, CollectionFactoryInterface>`.
- Создать `QueueConfig` для `queue`.
- Создать вложенные DTO для `queue`: `QueueConnectionConfig`, `QueueRegistryConfig`, `QueueInterceptorsConfig`, `QueuePipelineConfig`.
- В `QueueConfig` описать `default: string`, `aliases: array<string, string>`, `connections: array<string, QueueConnectionConfig>`, `registry`, `driverAliases: array<string, class-string>`, `interceptors`, `pipelines: array<string, QueuePipelineConfig>`, `defaultSerializer: SerializerInterface|string|Autowire|null`.
- В `QueueConnectionConfig` описать `driver: string`, `pipeline: ?string`.
- В `QueuePipelineConfig` описать `connector: CreateInfoInterface`, `consume: bool`.
- В `QueueRegistryConfig` описать `handlers: array<string, class-string>`, `serializers: array<string, string>`.
- В `QueueInterceptorsConfig` описать `push` и `consume` как списки `class-string|InterceptorInterface|CoreInterceptorInterface|Autowire`.
- Создать `StorageConfig` для `storage`.
- Создать вложенные DTO для `storage`: `StorageServerConfig`, `StorageLocalVisibilityConfig`, `StorageVisibilityModeConfig`, `StorageS3OptionsConfig`, `StorageBucketConfig`.
- В `StorageServerConfig` описать общие и текущие поля: `adapter: string`, `directory: ?string`, `visibility: StorageLocalVisibilityConfig|string|null`, `region: ?string`, `version: ?string`, `bucket: ?string`, `key: ?string`, `secret: ?string`, `token: ?string`, `expires: ?string`, `prefix: ?string`, `endpoint: ?string`, `options: ?StorageS3OptionsConfig`.
- В `StorageS3OptionsConfig` описать `usePathStyleEndpoint: bool`. Ключ `use_path_style_endpoint` должен маппиться через `ConvertKeysToCamelCase`.
- В `StorageBucketConfig` описать `server: string`, `bucket: ?string`, `distribution: ?string`, `visibility: ?string`, `prefix: ?string`, `region: ?string`.
- Добавить unit-тесты на преобразование каждого раздела из ручного массива и из реального `ConfiguratorInterface::getConfig()`.
- Добавить negative unit-тест для каждого нового root DTO.
- Добавить integration-тест на получение всех новых root DTO из контейнера.
- Проверить нативные config objects Spiral и Cycle минимум для queue, storage, session, mailer, database и cycle.

Результат: `database`, `cycle`, `queue` и `storage` доступны как typed config singleton, а framework-конфиги продолжают создаваться.

Сценарии тестирования:

- `database` сохраняет `logger.default: null`, aliases, databases и vendor driver objects.
- `cycle` сохраняет collection factory object, nullable options и nullable generators.
- `queue` сохраняет aliases, `defaultSerializer`, driver aliases, pipelines и registry.
- `storage` сохраняет local visibility, S3 nullable token/expires и `usePathStyleEndpoint`.
- Нативные Spiral и Cycle config classes создаются после добавления DTO.

Проверка:

- `composer test -- --filter Infrastructure\\\\Configuration`
- `composer test`
- `composer phpstan`

### 4. Финальная проверка и документация

Цель: закрыть задачу полным прогоном и оставить понятное описание новой практики для следующих конфигов.

Что сделать:

- Добавить в `docs/code-examples.md` короткий пример нового typed config: root DTO с `TypedConfig`, вложенный DTO, тест на `ConfigMapper`.
- Обновить `docs/rules.md`: добавить короткое правило, что для нового `app/config/*.php` сразу создаётся DTO в `Infrastructure/Configuration` и покрывается тестом.
- Убедиться, что список файлов `app/config/*.php` совпадает со списком root DTO, которые реализуют `TypedConfig`.
- Убедиться, что у каждого root DTO `configName()` совпадает с именем файла конфигурации.
- Убедиться, что пример из `docs/code-examples.md` не противоречит PHPStan-правилам проекта.
- Запустить полные проверки.

Результат: практика typed config закреплена в документации, все текущие конфиги покрыты DTO, проверки проходят.

Сценарии тестирования:

- Документация соответствует фактическому шаблону DTO.
- В проекте нет `app/config/*.php` без root DTO.
- В проекте нет root DTO без соответствующего файла `app/config/*.php`.
- Полный набор тестов и статический анализ проходят.

Проверка:

- `find app/config -maxdepth 1 -type f -name '*.php' | wc -l`
- `rg -n "implements TypedConfig|configName\\(\\)" app/src/Infrastructure/Configuration`
- `composer test`
- `composer phpstan`

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы исполнитель добавляет или обновляет тесты на новые DTO и сразу запускает проверки этой фазы. Для DTO обязательны три уровня проверки:

- unit-тест `ConfigMapper`: проверяет преобразование массива или vendor-объекта в конкретный DTO и читаемую ошибку при неверном типе;
- unit-тест с реальным `ConfiguratorInterface::getConfig()` после boot-а тестового приложения: проверяет default-поля Spiral bootloader-ов;
- integration-тест контейнера: проверяет, что root DTO зарегистрирован как singleton и что нативный Spiral config для того же раздела продолжает работать.

Отдельно нужны тесты:

- каждый `app/config/*.php` имеет root DTO с `TypedConfig`;
- каждый root DTO имеет `configName()` с именем файла;
- вложенные DTO без `TypedConfig` не регистрируются как config singleton;
- ошибка маппинга не раскрывает секретные значения.

Финально запускаются `composer test` и `composer phpstan`.

## Логирование

Стратегия: `debug_precise`.

В этой задаче не добавляются новые runtime-логи, потому что DTO создаются на старте приложения и могут содержать секреты. Точная диагностика должна оставаться в исключении `ConfigMappingException`: раздел конфигурации, DTO-класс, путь к ошибочному значению, ожидаемый тип и фактический тип. Значения вроде паролей, ключей S3, DSN и token не выводить в исключениях и логах.

## Документация и эксплуатация

- Обновить `docs/code-examples.md` примером typed config.
- Обновить `docs/rules.md` коротким правилом для новых config files.
- Env-файлы не менять, потому что DTO описывают уже существующие значения.
- Миграции не запускать, потому что база данных не меняется.
- Для локальной проверки в Docker можно использовать `make test` и `make phpstan`; внутри плана команды указаны как Composer-скрипты, потому что это проектные проверки из `composer.json` и `docs/rules.md`.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** требование описывать итоговый `ConfiguratorInterface::getConfig()` после default-ов Spiral bootloader-ов.
- **+ Добавлено:** фаза безопасной диагностики `ConfigMappingException`, чтобы ошибки не выводили DSN, S3 key, secret, token и password.
- **+ Добавлено:** точные поля для `migration`, `translator`, `scaffolder`, `cycle`, `database`, `queue`, `session`, `storage` и `mailer`.
- **+ Добавлено:** обязательные тесты на реальный `getConfig()`, совпадение `configName()` с именем файла и отсутствие лишних root DTO.
- **~ Изменено:** сложные конфиги вынесены в отдельную фазу, потому что там есть vendor-объекты, nullable-поля и вложенные map-секции.
- **~ Изменено:** логирование заменено на безопасную диагностику без сырого значения ошибки.
- **Отклонено:** отдельное логирование процесса маппинга не добавлено, потому что конфиги содержат секреты, а для отладки достаточно безопасного исключения и тестов.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-20_14-23_config-dto-for-missing-configs.md`

- [x] Шаг 1: Зафиксировать итоговую форму конфигов и безопасные ошибки
- [x] Шаг 2: Добавить DTO для простых и средних конфигов
- [x] Шаг 3: Добавить DTO для сложных framework-конфигов
- [x] Шаг 4: Финальная проверка и документация
