---
title: Публичные Composer-пакеты вместо tools
date: 2026-05-25 19:32
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
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

Коротко: подготовить локальные пакеты к публичной публикации в GitHub и Packagist: перенести их из `tools` в `packages`, переименовать директории, Composer-пакеты и PHP namespace, убрать привязку к YogaLoka, добавить автора и MIT-лицензию, улучшить русские README с примерами, настроить мультиязычные пользовательские тексты и исключения на русском и английском, а также подключить строгие PHPStan-правила к проверкам пакетов.

Готово считается тогда, когда приложение и все пакеты используют новые имена, в package-коде нет ссылок на старые `yoga-loka`, `Tools\*`, `tools/*` и `yoga_loka.*`, пакеты проверяются отдельно, а README каждого пакета можно читать как документацию публичного пакета.

## Контекст

- Сейчас есть четыре локальных Composer-пакета:
  - `tools/cqrs`
  - `tools/api-error`
  - `tools/openapi`
  - `tools/phpstan`
- Корневой `composer.json` подключает эти пакеты через path repositories из `tools/*`.
- Корневой `composer.json` также содержит старые package names в `require` и `require-dev`: `yoga-loka/api-error-tools`, `yoga-loka/cqrs-tools`, `yoga-loka/openapi-tools`, `yoga-loka/phpstan-rules`.
- Корневой `phpstan.neon` включает extension-файлы через `vendor/yoga-loka/phpstan-rules` и `vendor/yoga-loka/cqrs-tools`.
- В `tools/api-error/composer.json` есть зависимость от `yoga-loka/openapi-tools`.
- В `tools/cqrs/composer.json` есть dev-зависимость от `yoga-loka/phpstan-rules`.
- В коде пакетов используется namespace `Tools\Cqrs`, `Tools\ApiError`, `Tools\OpenApi`, `Tools\PHPStan`.
- В пакетах и документации есть старые Composer-имена `yoga-loka/*`.
- `tools/api-error` и `tools/openapi` уже используют Spiral translator для части пользовательских текстов, но ключи переводов сейчас начинаются с `yoga_loka.*`.
- В `tools/openapi` есть `extension.neon` с PHPStan-правилом `OpenApiAttributeRule`, но package-local `phpstan.neon` сейчас не включает этот extension.
- Корневой `.gitignore` игнорирует `/tools/*/vendor` и `/tools/*/runtime`; после переезда нужны явные правила для `/packages/*/vendor` и `/packages/*/runtime`.
- `tools/phpstan` является developer-инструментом. Пользователь подтвердил, что PHPStan-сообщения переводить не нужно.
- Правила проекта требуют, чтобы каждый пакет проверялся отдельно через свой `composer.json`, локальный `vendor`, `phpunit.xml`, `phpstan.neon` и package-local scripts.
- Новые внешние библиотеки для этой задачи не нужны. Используются уже существующие зависимости пакетов и собственный пакет строгих PHPStan-правил.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя от 2026-05-25.
- Публичный Composer vendor: `gian-tiaga`. Источник: ответ пользователя от 2026-05-25.
- Публичные имена пакетов:
  - `gian-tiaga/spiral-cqrs`
  - `gian-tiaga/spiral-api-errors`
  - `gian-tiaga/spiral-openapi`
  - `gian-tiaga/phpstan-strict-rules`
- Имена директорий после переноса:
  - `packages/spiral-cqrs`
  - `packages/spiral-api-errors`
  - `packages/spiral-openapi`
  - `packages/phpstan-strict-rules`
- PHP namespaces:
  - `GianTiaga\SpiralCqrs`
  - `GianTiaga\SpiralApiErrors`
  - `GianTiaga\SpiralOpenApi`
  - `GianTiaga\PhpStanStrictRules`
- Опечатки из исходного запроса вроде `sprial` и `ApiErrir` не переносить в код и имена пакетов. Использовать корректные `spiral` и `api-errors`. Источник: подтверждённый пользователем вариант имён.
- Автор во всех package `composer.json`: `Ildar Enakaev <ienakaev@ya.ru>`. Источник: прямой запрос пользователя.
- Лицензия во всех пакетах: MIT. Добавить отдельный `LICENSE` в каждый пакет при уже указанном `license: MIT` в `composer.json`. Источник: прямой запрос пользователя.
- README каждого пакета оставить на русском языке. Источник: прямой запрос пользователя.
- Мультиязычность:
  - переводить пользовательские тексты и package-owned исключения;
  - поддерживать `ru` и `en`;
  - не переводить PHPStan diagnostics и rule messages;
  - доменные сообщения приложения не переводить внутри пакетов, потому что они принадлежат приложению, а не package-коду.
  Источник: ответ пользователя от 2026-05-25.
- Ключи переводов заменить со старых `yoga_loka.*` на package-neutral ключи:
  - `gian_tiaga.spiral_api_errors.*`
  - `gian_tiaga.spiral_openapi.*`
  - `gian_tiaga.spiral_cqrs.*`
- PHPStan error identifiers заменить с проектных `project.*` и старых `cqrs.*` на package-neutral identifiers:
  - `gian_tiaga.phpstan_strict_rules.*`
  - `gian_tiaga.spiral_cqrs.*`
- PHPStan identifiers из `spiral-openapi` заменить с `openApi.*` на `gian_tiaga.spiral_openapi.*`.
- `packages/phpstan-strict-rules` не добавляет сам себя в `require-dev`, потому что Composer-пакет не должен зависеть сам от себя. Он проверяет себя через локальный `extension.neon`. Остальные пакеты подключают его в `require-dev`. Источник: `decision_mode: autonomous`, причина — техническое ограничение Composer.
- Переименование package names, namespaces, translation keys и PHPStan identifiers считается breaking change. Обратные aliases, `replace` для старых `yoga-loka/*` и совместимые старые translation keys не добавляются, потому что пакеты готовятся к первой публичной публикации и старые имена являются внутренними именами проекта. В README каждого пакета добавить короткий раздел миграции со старых локальных имён. Источник: `decision_mode: autonomous`, причина — отсутствие публичного релиза со старым контрактом.
- Для публичных пакетов правило проекта «исключения на русском» применяется так: пользовательские тексты и package-owned исключения в runtime получают текст на текущем locale `ru` или `en`. PHPStan diagnostics остаются на английском по подтверждению пользователя. Низкоуровневые технические исключения, которые не отдаются пользователю и не имеют доступа к Spiral translator без глобального состояния, остаются developer-facing и описываются в README как технические ошибки. Источник: ответ пользователя и `decision_mode: autonomous`, причина — нельзя безопасно протаскивать переводчик в value-like DTO и чистые утилиты ради текста исключения.

## Целевой алгоритм

1. Разработчик устанавливает зависимости корневого приложения.
2. Composer берёт локальные пакеты из `packages/*` через path repositories.
3. Приложение импортирует классы из `GianTiaga\SpiralCqrs`, `GianTiaga\SpiralApiErrors` и `GianTiaga\SpiralOpenApi`.
4. Пакеты `spiral-api-errors`, `spiral-openapi` и `spiral-cqrs` подключают свои locale-каталоги через Spiral translator там, где у них есть пользовательские тексты или package-owned исключения.
5. При текущем locale `ru` пользовательские тексты и package-owned исключения возвращаются на русском, при `en` — на английском.
6. Пакет `phpstan-strict-rules` отдаёт PHPStan diagnostics на английском и сохраняет стабильные package-neutral identifiers.
7. При запуске PHPStan корневое приложение и пакеты включают strict-rules extension из `vendor/gian-tiaga/phpstan-strict-rules`.
8. При генерации OpenAPI существующие debug-события остаются точными: старт генерации, namespace, файл результата, число найденных файлов и классов, число операций и схем, запись YAML. Для нового отдельного шага генерации или проверки конфигурации добавляется отдельный debug-лог.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

HTTP API приложения не меняется.

Меняются публичные контракты Composer-пакетов:

- Composer package names меняются с `yoga-loka/*` на `gian-tiaga/*`.
- Composer `require` и `require-dev` меняются на новые package names во всех корневых и package-local `composer.json`.
- PHP namespaces меняются с `Tools\*` на `GianTiaga\*`.
- Composer path repositories меняются с `tools/*` на `packages/*`.
- PHPStan extension paths меняются с `vendor/yoga-loka/*` на `vendor/gian-tiaga/*`.
- Locale keys меняются с `yoga_loka.*` на `gian_tiaga.*`.
- PHPStan identifiers меняются с проектных `project.*`, старых `cqrs.*` и `openApi.*` на `gian_tiaga.*`.
- README больше не описывает установку через `tools/*` как основной способ. Основной способ — `composer require gian-tiaga/<package>`.

## Фазы выполнения

### 1. Перенести директории и сменить публичные имена

Цель: сделать новую структуру пакетов и обновить все механические ссылки на неё.

Что сделать:

- Перенести директорию `tools` в `packages`.
- Переименовать пакеты:
  - `tools/cqrs` -> `packages/spiral-cqrs`
  - `tools/api-error` -> `packages/spiral-api-errors`
  - `tools/openapi` -> `packages/spiral-openapi`
  - `tools/phpstan` -> `packages/phpstan-strict-rules`
- В каждом package `composer.json` обновить `name`, `autoload`, `autoload-dev`, `repositories`, `description`, `authors`, `license`.
- В корневом `composer.json` заменить `require`:
  - `yoga-loka/api-error-tools` -> `gian-tiaga/spiral-api-errors`
  - `yoga-loka/cqrs-tools` -> `gian-tiaga/spiral-cqrs`
  - `yoga-loka/openapi-tools` -> `gian-tiaga/spiral-openapi`
- В корневом `composer.json` заменить `require-dev`:
  - `yoga-loka/phpstan-rules` -> `gian-tiaga/phpstan-strict-rules`
- В `packages/spiral-api-errors/composer.json` заменить production-зависимость `yoga-loka/openapi-tools` на `gian-tiaga/spiral-openapi`.
- В `packages/spiral-cqrs/composer.json` заменить dev-зависимость `yoga-loka/phpstan-rules` на `gian-tiaga/phpstan-strict-rules`.
- В корневом `composer.json` заменить path repositories на `packages/*`.
- В корневом `phpstan.neon` заменить include-пути на `vendor/gian-tiaga/*`.
- В package `phpstan.neon`, `extension.neon`, `phpunit.xml`, `bootstrap.php` обновить пути после переезда.
- В `.gitignore` заменить package-local ignore-правила на `/packages/*/vendor` и `/packages/*/runtime`; старые `/tools/*/vendor` и `/tools/*/runtime` удалить после удаления директории `tools`.
- В `docs/rules.md` сразу заменить правило про `tools/*` на `packages/*`, чтобы следующие фазы не противоречили актуальным правилам проекта.
- Массово заменить PHP namespace и imports:
  - `Tools\Cqrs` -> `GianTiaga\SpiralCqrs`
  - `Tools\ApiError` -> `GianTiaga\SpiralApiErrors`
  - `Tools\OpenApi` -> `GianTiaga\SpiralOpenApi`
  - `Tools\PHPStan` -> `GianTiaga\PhpStanStrictRules`
- Заменить строковые ссылки в fixture/stub-файлах, `*.fixture`, assert-строках тестов и примерах кода, а не только `use` imports.
- Обновить lock-файлы в таком порядке:
  - `composer -d packages/phpstan-strict-rules update --lock`
  - `composer -d packages/spiral-openapi update --lock`
  - `composer -d packages/spiral-cqrs update --lock`
  - `composer -d packages/spiral-api-errors update --lock`
  - `composer update gian-tiaga/phpstan-strict-rules gian-tiaga/spiral-openapi gian-tiaga/spiral-cqrs gian-tiaga/spiral-api-errors --with-dependencies`

Результат: приложение и пакеты собираются с новой директорией `packages` и новыми Composer/PHP именами.

Сценарии тестирования:

- Composer видит все четыре пакета по новым именам.
- Autoload работает для production-классов и тестов каждого пакета.
- Корневой PHPStan включает новые extension-файлы.
- Старые namespaces не используются в production-коде.

Проверка:

- `composer validate --strict --no-interaction`
- `composer install`
- `composer dump-autoload`
- `composer -d packages/spiral-cqrs validate --strict --no-interaction`
- `composer -d packages/spiral-api-errors validate --strict --no-interaction`
- `composer -d packages/spiral-openapi validate --strict --no-interaction`
- `composer -d packages/phpstan-strict-rules validate --strict --no-interaction`
- `composer -d packages/spiral-cqrs install`
- `composer -d packages/spiral-api-errors install`
- `composer -d packages/spiral-openapi install`
- `composer -d packages/phpstan-strict-rules install`
- `rg -n "Tools\\\\|vendor/yoga-loka|yoga-loka/(api-error-tools|cqrs-tools|openapi-tools|phpstan-rules)|tools/" app tests composer.json phpstan.neon docs/rules.md docs/code-examples.md packages -g '!packages/*/vendor/**' -g '!packages/*/runtime/**'`

### 2. Убрать проектные привязки из package-кода

Цель: сделать пакеты переносимыми и не привязанными к YogaLoka.

Что сделать:

- Проверить package production-код, тесты, README, `composer.json`, `phpstan.neon`, `extension.neon`, `phpunit.xml` на старые проектные следы:
  - `YogaLoka`
  - `Yoga Loka`
  - `yoga-loka`
  - `yoga_loka`
  - `App\`
  - `tools/`
  - `Tools\`
- В package production-коде убрать все найденные проектные привязки.
- В тестах оставить только нейтральные fixture namespaces, не завязанные на `App\` и YogaLoka.
- В `packages/phpstan-strict-rules` заменить PHPStan identifiers `project.*` на `gian_tiaga.phpstan_strict_rules.*`.
- В `packages/spiral-cqrs` заменить identifiers `cqrs.*` на `gian_tiaga.spiral_cqrs.*`.
- В `packages/spiral-openapi` заменить identifiers `openApi.*` на `gian_tiaga.spiral_openapi.*`.
- Обновить тесты PHPStan-правил под новые identifiers.
- Добавить тесты для `OpenApiAttributeRule` и проверить пустой, некорректный и повторяющийся `id`.
- Добавить или обновить portability-тесты для каждого пакета:
  - production-код не содержит `App\`;
  - production-код не содержит `YogaLoka`, `yoga-loka`, `yoga_loka`;
  - production-код не содержит старые `Tools\*` namespaces.

Результат: package-код можно вынести в публичный репозиторий без привязки к текущему проекту.

Сценарии тестирования:

- Portability-тесты падают, если в package production-код вернётся `App\`, `YogaLoka`, `yoga-loka`, `yoga_loka` или `Tools\`.
- PHPStan-тесты ожидают новые identifiers.
- `spiral-openapi` покрывает своё PHPStan-правило тестом.
- Старые identifiers не остаются в README и extension-конфигурации.

Проверка:

- `composer -d packages/spiral-cqrs test`
- `composer -d packages/spiral-api-errors test`
- `composer -d packages/spiral-openapi test`
- `composer -d packages/phpstan-strict-rules test`
- `composer -d packages/spiral-cqrs phpstan`
- `composer -d packages/spiral-api-errors phpstan`
- `composer -d packages/spiral-openapi phpstan`
- `composer -d packages/phpstan-strict-rules phpstan`
- `rg -n "YogaLoka|Yoga Loka|yoga-loka|yoga_loka|Tools\\\\|App\\\\" packages -g '!packages/*/vendor/**' -g '!packages/*/runtime/**'`

### 3. Настроить мультиязычность пользовательских текстов и исключений

Цель: все тексты, которыми владеют пакеты и которые может увидеть пользователь или разработчик как package-owned исключение, должны иметь русский и английский варианты. PHPStan diagnostics не переводить.

Что сделать:

- В `packages/spiral-api-errors` заменить ключи переводов:
  - `yoga_loka.api_error.route_not_found` -> `gian_tiaga.spiral_api_errors.route_not_found`
  - `yoga_loka.api_error.validation_error` -> `gian_tiaga.spiral_api_errors.validation_error`
  - `yoga_loka.api_error.internal_server_error` -> `gian_tiaga.spiral_api_errors.internal_server_error`
- В `packages/spiral-openapi` заменить ключи переводов:
  - `yoga_loka.openapi.successful_response` -> `gian_tiaga.spiral_openapi.successful_response`
  - `yoga_loka.openapi.api_error` -> `gian_tiaga.spiral_openapi.api_error`
- В `spiral-cqrs` добавить locale-файлы `locale/ru/messages.php` и `locale/en/messages.php`, подключить их в `CqrsBootloader` через `I18nBootloader`, потому что в package-owned runtime-исключениях появятся ключи переводов.
- В `spiral-cqrs` перевести runtime-исключения middleware pipeline:
  - `Handler middleware должен реализовывать HandlerMiddlewareInterface.`
  - `Некорректный атрибут для middleware логирования операции.`
  - `Некорректный атрибут для транзакционного middleware.`
  - `Атрибут #[Transactional] поддерживается только для Command Handler.`
- В `spiral-openapi` перевести package-owned исключения, которые создаются в процессе генерации через DI-managed generator/parser/spec/writer:
  - ошибки конфигурации `OpenApiGeneratorConfig::validate`;
  - ошибки парсинга PHP-файла;
  - ошибки повторяющегося `operationId`;
  - ошибки неизвестного response wrapper и неизвестного типа схемы;
  - ошибки записи YAML.
- `FileResponseException` в `FileResponse` оставить developer-facing техническим исключением, потому что `FileResponse` является response DTO и не должен получать translator или глобальное состояние. В README явно описать, что ошибки отсутствующего или нечитаемого локального файла являются ошибками разработки и не переводятся пакетом.
- В `spiral-api-errors` не переводить сообщения доменных исключений приложения. Пакет только передаёт уже готовое сообщение для ожидаемых 4xx.
- В `spiral-openapi` не переводить описания из `#[OpenApi(description: ...)]` и PHPDoc, потому что эти тексты принадлежат приложению.
- В `phpstan-strict-rules` оставить rule messages на английском.
- Обновить locale-файлы `locale/ru/messages.php` и `locale/en/messages.php`.
- Проверить, что `spiral-api-errors`, `spiral-openapi` и `spiral-cqrs` имеют прямую Composer-зависимость, через которую доступен `Spiral\Translator\TranslatorInterface`; текущая зависимость `spiral/framework` сохраняется.
- Обновить тесты на русские и английские тексты.
- Добавить тесты, которые проверяют реальную загрузку package locale-каталогов через bootloader, а не только `FakeTranslator`.

Результат: package-owned пользовательские тексты и поддерживаемые исключения работают на `ru` и `en`, а PHPStan diagnostics остаются английскими.

Сценарии тестирования:

- `spiral-api-errors` отдаёт 404, 422 и 500 на русском и английском.
- `spiral-openapi` генерирует стандартные описания response на русском и английском.
- `spiral-cqrs` возвращает переведённые package-owned runtime-исключения на русском и английском.
- `spiral-openapi` возвращает переведённые package-owned исключения генерации на русском и английском.
- `FileResponseException` покрыт README как developer-facing техническое исключение.
- PHPStan diagnostics остаются на английском, но identifiers становятся package-neutral.
- Старые ключи `yoga_loka.*` не используются.
- Locale-каталоги реально подключаются через bootloader после переезда в `packages/*`.

Проверка:

- `composer -d packages/spiral-api-errors test`
- `composer -d packages/spiral-openapi test`
- `composer -d packages/spiral-cqrs test`
- `composer -d packages/phpstan-strict-rules test`
- `composer -d packages/spiral-api-errors phpstan`
- `composer -d packages/spiral-openapi phpstan`
- `composer -d packages/spiral-cqrs phpstan`
- `composer -d packages/phpstan-strict-rules phpstan`
- `rg -n "yoga_loka\\.|Route not found|Validation error|Internal server error" packages/spiral-api-errors packages/spiral-openapi packages/spiral-cqrs -g '!packages/*/vendor/**' -g '!packages/*/runtime/**'`

### 4. Подключить строгие PHPStan-правила ко всем пакетам

Цель: каждый пакет должен проверяться тем же набором строгих правил, кроме технически невозможной self-dependency.

Что сделать:

- В `packages/spiral-cqrs/composer.json` добавить `gian-tiaga/phpstan-strict-rules` в `require-dev`.
- В `packages/spiral-api-errors/composer.json` добавить `gian-tiaga/phpstan-strict-rules` в `require-dev`.
- В `packages/spiral-openapi/composer.json` добавить `gian-tiaga/phpstan-strict-rules` в `require-dev`.
- В этих трёх пакетах добавить path repository на `../phpstan-strict-rules`.
- В их `phpstan.neon` подключить `vendor/gian-tiaga/phpstan-strict-rules/extension.neon`.
- В `packages/spiral-openapi/phpstan.neon` подключить свой `extension.neon`, чтобы `OpenApiAttributeRule` реально проверялся внутри пакета.
- В корневом `phpstan.neon` подключить `vendor/gian-tiaga/spiral-openapi/extension.neon`, потому что приложение использует `#[OpenApi(...)]` в HTTP-контроллерах.
- В `packages/phpstan-strict-rules/phpstan.neon` подключить свой локальный `extension.neon` напрямую, без Composer self-dependency.
- Обновить lock-файлы.
- Исправить найденные нарушениями strict-rules проблемы только в пределах package-кода.

Результат: строгие правила реально участвуют в проверках всех пакетов.

Сценарии тестирования:

- PHPStan каждого пакета загружает strict-rules extension.
- `spiral-openapi` загружает собственный OpenAPI PHPStan extension.
- Корневой PHPStan проверяет OpenAPI attributes приложения.
- Пакеты не используют корневой `vendor`.
- Пакет `phpstan-strict-rules` не зависит сам от себя.

Проверка:

- `composer -d packages/spiral-cqrs install`
- `composer -d packages/spiral-api-errors install`
- `composer -d packages/spiral-openapi install`
- `composer -d packages/phpstan-strict-rules install`
- `composer -d packages/spiral-cqrs phpstan`
- `composer -d packages/spiral-api-errors phpstan`
- `composer -d packages/spiral-openapi phpstan`
- `composer -d packages/phpstan-strict-rules phpstan`
- `rg -n "../../vendor|vendor/yoga-loka|tools/phpstan|tools/" packages/*/phpstan.neon packages/*/composer.json packages/*/bootstrap.php`

### 5. Переписать README и добавить лицензии

Цель: сделать документацию пакетов понятной для публичного использования.

Что сделать:

- В каждом пакете создать или обновить `README.md` на русском.
- В README каждого пакета убрать основную установку через path repository.
- Описать установку через Packagist:
  - `composer require gian-tiaga/spiral-cqrs`
  - `composer require gian-tiaga/spiral-api-errors`
  - `composer require gian-tiaga/spiral-openapi`
  - `composer require --dev gian-tiaga/phpstan-strict-rules`
- Для локальной разработки упоминать path repository только в отдельном коротком разделе, без привязки к YogaLoka.
- В README `spiral-cqrs` показать:
  - подключение bootloader-а;
  - пример Command и Handler;
  - пример Query и Handler;
  - `#[Transactional]`;
  - `#[LogOperation]`;
  - PHPStan-правило для callable `handle(...)`.
- В README `spiral-api-errors` показать:
  - подключение bootloader-а;
  - порядок middleware и interceptors;
  - формат 404, 422 и 500;
  - работу locale `ru` и `en`;
  - границу ответственности: доменные сообщения приложения пакет не переводит.
- В README `spiral-openapi` показать:
  - подключение bootloader-а;
  - минимальный конфиг генерации;
  - response wrappers;
  - file responses;
  - генерацию YAML на выбранном locale;
  - пример PHPDoc generic для controller response.
  - подключение PHPStan extension для проверки `#[OpenApi]`.
- В README `phpstan-strict-rules` показать:
  - подключение extension;
  - список правил;
  - примеры плохого и хорошего кода;
  - английские diagnostics как осознанный контракт developer-инструмента.
- Добавить MIT `LICENSE` в каждый пакет.
- В package `composer.json` добавить `authors`.
- В README каждого пакета добавить короткий раздел миграции со старого локального имени на новое публичное имя.

Результат: каждый пакет имеет самостоятельный русский README, MIT license и авторство.

Сценарии тестирования:

- README не содержит `YogaLoka`, `yoga-loka/*`, `Tools\*`, `tools/*` как основной способ установки.
- Примеры используют новые namespaces.
- Лицензия есть в каждом пакете.
- Composer validate не ругается на authors/license.

Проверка:

- `composer -d packages/spiral-cqrs validate --strict --no-interaction`
- `composer -d packages/spiral-api-errors validate --strict --no-interaction`
- `composer -d packages/spiral-openapi validate --strict --no-interaction`
- `composer -d packages/phpstan-strict-rules validate --strict --no-interaction`
- `find packages -maxdepth 2 -name LICENSE -print`
- `rg -n "YogaLoka|Yoga Loka|yoga-loka/|Tools\\\\|tools/" packages/*/README.md`

### 6. Обновить приложение, проектную документацию и полный набор проверок

Цель: синхронизировать корневой проект с новыми пакетами и доказать, что всё работает вместе.

Что сделать:

- Обновить imports в `app/src`, тестах приложения и проектных конфигурациях на новые namespaces.
- Обновить `docs/arch.md`: заменить описание `tools/*` на `packages/*`, новые Composer-имена, namespaces и ключи переводов.
- Проверить `docs/code-examples.md` и заменить старые imports или package paths.
- Обновить корневой lock-файл после изменения path repositories и package names.
- Запустить проверки отдельных пакетов и всего приложения.
- При ошибках исправлять только строки, связанные с переездом, именами, мультиязычностью, README, лицензией и strict-rules.

Результат: корневое приложение и все пакеты проверяются с новой структурой.

Сценарии тестирования:

- Приложение использует новые package namespaces.
- Корневой PHPStan проходит с новыми extension paths.
- Все package-local test/phpstan проходят.
- В актуальной документации проекта больше нет старого правила про `tools/*`.
- Полный набор Docker-проверок приложения проходит.

Проверка:

- `composer install`
- `composer dump-autoload`
- `composer -d packages/spiral-cqrs test`
- `composer -d packages/spiral-api-errors test`
- `composer -d packages/spiral-openapi test`
- `composer -d packages/phpstan-strict-rules test`
- `composer -d packages/spiral-cqrs phpstan`
- `composer -d packages/spiral-api-errors phpstan`
- `composer -d packages/spiral-openapi phpstan`
- `composer -d packages/phpstan-strict-rules phpstan`
- `make phpstan`
- `make test`
- `rg -n "tools/|Tools\\\\|vendor/yoga-loka|yoga-loka/(api-error-tools|cqrs-tools|openapi-tools|phpstan-rules)|yoga_loka\\.(api_error|openapi)" app tests composer.json phpstan.neon docs/arch.md docs/rules.md docs/code-examples.md packages -g '!packages/*/vendor/**' -g '!packages/*/runtime/**'`
- `rg -n "tools/|Tools\\\\|vendor/yoga-loka|yoga-loka/(api-error-tools|cqrs-tools|openapi-tools|phpstan-rules)|yoga_loka\\.(api_error|openapi)" docs -g '!docs/plans/**' -g '!docs/executions/**' -g '!docs/fixes/**' -g '!docs/reviews/**' -g '!docs/review-fixes/**' -g '!docs/researches/**'`

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы нужно писать или обновлять тесты, которые относятся к этой фазе, и запускать проверки этой фазы сразу. В конце обязательно запустить проверки всех пакетов и приложения.

Минимальный набор:

- package-local PHPUnit для каждого пакета;
- package-local PHPStan для каждого пакета;
- Composer validate для каждого package `composer.json`;
- portability-тесты на отсутствие проектных привязок в package production-коде;
- тесты переводов `ru` и `en` для `spiral-api-errors`, `spiral-openapi` и всех переведённых package-owned исключений;
- тесты реального подключения locale-каталогов через package bootloader;
- тесты `OpenApiAttributeRule` с новыми identifiers;
- корневой `make phpstan`;
- корневой `make test`.

## Логирование

Стратегия: `debug_precise`.

Новых бизнес-сценариев задача не добавляет. Поэтому новые логи нужны только там, где изменение создаёт новый важный runtime-шаг.

Правила для реализации:

- В `spiral-openapi` сохранить точные debug-сообщения генерации: старт, namespace, файл результата, число файлов, число классов, запись YAML, количество операций и схем.
- Для отдельного шага проверки конфигурации или загрузки переводов добавить debug-сообщение без секретов и персональных данных.
- В `spiral-cqrs` сохранить operation debug-логи `#[LogOperation]`; не добавлять лишний шум при обычном dispatch без атрибута.
- Логи не являются пользовательскими текстами этой задачи и не переводятся специально, потому что они не попадают в пользовательский ответ.

## Документация и эксплуатация

- README каждого пакета — основной публичный документ.
- `docs/arch.md` и `docs/rules.md` должны отражать новую директорию `packages`.
- После реализации локальные команды проверки пакетов меняются с `composer -d tools/<package> ...` на `composer -d packages/<package> ...`.
- Старые package names, namespaces, translation keys и PHPStan identifiers не поддерживаются как совместимый публичный контракт. В README это описывается как миграция с локальных имён до первого публичного релиза.
- Перед публикацией в GitHub и Packagist отдельно проверить:
  - package name в Packagist свободен или принадлежит автору;
  - репозитории GitHub созданы под выбранные пакеты;
  - version tags будут ставиться отдельно для каждого пакета;
  - `composer validate --strict` проходит в каждом публичном репозитории.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** явная замена Composer `require` и `require-dev` в корневом проекте и package-local зависимостях.
- **+ Добавлено:** обновление `.gitignore` для `/packages/*/vendor` и `/packages/*/runtime`.
- **+ Добавлено:** единая политика PHPStan identifiers, включая `openApi.*`, и тесты для `OpenApiAttributeRule`.
- **+ Добавлено:** точный порядок обновления package-local и корневого lock-файлов.
- **+ Добавлено:** тесты реального подключения locale-каталогов через bootloader.
- **~ Изменено:** финальные `rg`-проверки разделены так, чтобы не ломаться на исторических документах и package-local `vendor/runtime`.
- **~ Изменено:** мультиязычность исключений уточнена: runtime package-owned исключения переводятся, `FileResponseException` остаётся developer-facing без глобального translator.
- **~ Изменено:** документация теперь должна описывать breaking change и миграцию со старых локальных имён.
- **Отклонено:** полная обратная совместимость через `replace`, старые namespaces, старые translation keys и старые PHPStan identifiers не добавляется, потому что пакеты готовятся к первому публичному релизу, а старые имена были внутренними.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-25_19-48_public-packages-rename-readme-i18n.md`

- [x] Шаг 1: Перенести директории и сменить публичные имена
- [x] Шаг 2: Убрать проектные привязки из package-кода
- [x] Шаг 3: Настроить мультиязычность пользовательских текстов и исключений
- [x] Шаг 4: Подключить строгие PHPStan-правила ко всем пакетам
- [x] Шаг 5: Переписать README и добавить лицензии
- [x] Шаг 6: Обновить приложение, проектную документацию и полный набор проверок

## Изменения во время выполнения

- Пользователь изменил решение по exception-сообщениям: все исключения остаются на русском языке. Переводы `ru/en` применяются только к пользовательским текстам ответов и OpenAPI-описаниям, которые не являются сообщениями исключений.
