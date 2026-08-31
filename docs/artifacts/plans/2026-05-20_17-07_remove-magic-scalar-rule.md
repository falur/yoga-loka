---
title: Убрать правило magic scalar и упростить строковые константы
date: 2026-05-20 17:07
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

Убрать PHPStan-правило `project.magicScalarLiteral`, потому что оно слишком широко запрещает нормальные строки и приводит к лишним константам. После удаления правила пройтись по проекту и пакетам, вернуть простые строки там, где строковая константа не даёт смысла и выглядит как оверинжиниринг.

Готовый результат: PHPStan больше не регистрирует `DisallowMagicScalarLiteralRule`, локальные `@phpstan-ignore project.magicScalarLiteral` удалены, а таблица ниже реализована точечно без удаления полезных публичных или доменных констант.

## Контекст

- Правило зарегистрировано в `tools/phpstan/extension.neon` как сервис `Tools\PHPStan\Rules\DisallowMagicScalarLiteralRule`.
- У правила есть отдельные тесты и fixtures: `DisallowMagicScalarLiteralRuleTest`, `MagicScalarLiteralsAllowed.php`, `MagicScalarLiteralsForbidden.php`.
- В `docs/rules.md` сейчас есть правило «Константы вместо magic values», которое прямо ссылается на `project.magicScalarLiteral`.
- В `app/src/Endpoint/Temporal/Ping.php` остался локальный ignore для `'pong'`.
- В рабочем дереве уже есть незакоммиченные изменения по typed config и предыдущей попытке исключения для `configName()`. Исполнитель должен не откатывать чужие изменения, а привести итог к состоянию этого плана.

## Принятые решения

- Правило `project.magicScalarLiteral` удаляется из активного PHPStan tooling полностью: регистрация, класс, unit-тест и fixtures. Источник: прямой запрос пользователя.
- Не добавлять новые исключения в это правило и не оставлять его выключенным «на потом»: мёртвый код в пакете правил не нужен.
- `docs/rules.md` больше не должен запрещать обычные runtime-строки через общий запрет. Вместо старого пункта добавить точный новый пункт: `**Не плодить технические константы**: одноразовые технические строки, шаблоны и сообщения оставлять рядом с использованием. Константу, enum или value object использовать, когда значение переиспользуется, является публичным контрактом или закрытым набором вариантов.`
- Исторические документы в `docs/fixes/`, `docs/executions/`, старые `docs/plans/` не переписывать. Они описывают уже выполненную историю.
- Ожидаемый объём: 3 фазы, примерно 15-20 файлов, в основном удаления и точечный возврат строк.

## Целевой алгоритм

1. PHPStan загружает `tools/phpstan/extension.neon`.
2. В списке правил больше нет `DisallowMagicScalarLiteralRule`.
3. Анализ `app/src` больше не выдаёт ошибку `project.magicScalarLiteral`.
4. Код больше не держит технические строки в одноразовых константах только ради этого правила.
5. Полезные именованные значения остаются, если они являются публичным контрактом, набором вариантов или реально переиспользуются как смысловое имя.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Не затрагивается.

## Таблица замен для валидации

| # | Файл | Сейчас | Плановая замена | Причина |
|---|------|--------|-----------------|---------|
| 1 | `tools/phpstan/extension.neon` | Сервис `DisallowMagicScalarLiteralRule` зарегистрирован с тегом `phpstan.rules.rule` | Удалить регистрацию этого сервиса | PHPStan больше не должен запускать широкое правило |
| 2 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Отдельный класс правила с большим списком исключений | Удалить файл | Правило признано вредным и не должно оставаться мёртвым кодом |
| 3 | `tools/phpstan/tests/Unit/PHPStan/DisallowMagicScalarLiteralRuleTest.php` | Unit-тест удаляемого правила | Удалить файл | Тест относится только к удаляемому правилу |
| 4 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Fixture удаляемого правила | Удалить файл | Fixture больше не используется |
| 5 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsForbidden.php` | Fixture удаляемого правила | Удалить файл | Fixture больше не используется |
| 6 | `tools/phpstan/README.md` | Раздел `project.magicScalarLiteral` | Удалить раздел целиком | Документация не должна описывать удалённое правило |
| 7 | `docs/rules.md` | Правило «Константы вместо magic values» с привязкой к PHPStan | Заменить на точный новый пункт из раздела «Принятые решения» | Убрать конфликт с новой политикой простых строк |
| 8 | `app/src/Endpoint/Temporal/Ping.php` | `return 'pong'; // @phpstan-ignore project.magicScalarLiteral` | `return 'pong';` | Локальное подавление больше не нужно |
| 9 | Root typed config DTO в `app/src/Infrastructure/Configuration/*/*Config.php` | В текущем дереве `configName()` уже без `@phpstan-ignore` | Оставить как есть и проверить, что ignore не вернулся | Учитывает текущий dirty state и не создаёт лишней работы |
| 10 | `docs/code-examples.md` | В текущем дереве пример typed config уже без `@phpstan-ignore` | Оставить как есть и проверить, что ignore не вернулся | Документация уже показывает новый стиль |
| 11 | `app/src/Infrastructure/Framework/Bootloader/LoggingBootloader.php` | `HTTP_LOG_FILE`, `ERROR_LOG_FILE`, `DEBUG_LOG_FILE` | Inline-строки `'logs/http.log'`, `'logs/error.log'`, `'logs/debug.log'` | Константы только прячут путь; `ERROR_LOG_MAX_FILES` оставить |
| 12 | `tools/api-error/src/Interceptor/ApiExceptionInterceptor.php` | `INTERNAL_SERVER_ERROR_MESSAGE` | Inline-строка `'Внутренняя ошибка сервера'` в `errorResponse()` | Это одно сообщение ответа в одном месте |
| 13 | `tools/openapi/src/Response/FileContentResponse.php` | `CONTENT_DISPOSITION_INLINE` | Inline-строка `'inline'` в заголовке | Одноразовое HTTP-значение рядом с header enum читается проще |
| 14 | `tools/openapi/src/Response/HtmlResponse.php` | `CONTENT_DISPOSITION_INLINE` | Inline-строка `'inline'` в заголовке | Одноразовое HTTP-значение рядом с header enum читается проще |
| 15 | `tools/openapi/src/Response/FileResponse.php` | `CONTENT_DISPOSITION_ATTACHMENT_TEMPLATE` | Inline-шаблон `\sprintf('attachment; filename="%s"', ...)` | Шаблон используется один раз |
| 16 | `tools/openapi/tests/Response/FileResponseTest.php` | `FILE_BODY`, `FILE_NAME`, `CONTENT_DISPOSITION_INLINE`, `CONTENT_DISPOSITION_ATTACHMENT` | Inline-строки `'file body'`, `'export.txt'`, `'inline'`, `'attachment; filename="export.txt"'` в тестах | Тестовые значения короткие и понятные прямо в проверках |

## Что не менять

| Файл | Что оставить | Почему |
|------|--------------|--------|
| `app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php` | `GROUP_API`, `GROUP_WEB` | Это публичные имена групп маршрутов, `GROUP_API` используется внешним тестовым bootloader-ом |
| `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | `CONFIGURATION_DIRECTORY`, `CONFIGURATION_NAMESPACE_PREFIX`, `CONFIG_FILE_PATTERN` | Эти значения описывают контракт автопоиска typed config и делают алгоритм понятнее |
| `tools/openapi/src/Model/PropertyMetadata.php` | `SOURCE_QUERY`, `SOURCE_PATH`, `SOURCE_BODY`, `SOURCE_DATA`, `SOURCE_NONE` | Это набор вариантов, который связывает parser и builder; лучше оставить именованным контрактом |
| `tools/phpstan/src/TypeContracts/TypeContractViolation.php` | Публичные identifier-константы | Это публичные идентификаторы ошибок другого правила |
| Остальные классы PHPStan-правил | `ERROR_MESSAGE`, `ERROR_IDENTIFIER` и похожие константы | Это локальный стиль оставшихся правил, задача не про их переписывание |
| `docs/fixes/`, `docs/executions/`, старые `docs/plans/` | Исторические упоминания `project.magicScalarLiteral` | Старые документы фиксируют историю и не должны переписываться |

## Фазы выполнения

### 1. Удалить PHPStan-правило

Цель: убрать широкий запрет из активной проверки и удалить связанный мёртвый код.

Что сделать:
- Удалить регистрацию `DisallowMagicScalarLiteralRule` из `tools/phpstan/extension.neon`.
- Удалить класс правила и его тесты с fixtures.
- Удалить раздел `project.magicScalarLiteral` из `tools/phpstan/README.md`.
- Заменить старый пункт `docs/rules.md` на новый текст из раздела «Принятые решения».

Результат: PHPStan tooling больше не знает правило `project.magicScalarLiteral`.

Сценарии тестирования:
- Пакет PHPStan-правил загружается без ссылки на удалённый класс.
- В README и правилах проекта нет актуального требования использовать константы для любых runtime-строк.

Проверка:
- `composer tools:phpstan:qa`
- `rg -n "DisallowMagicScalarLiteralRule|project\\.magicScalarLiteral" tools/phpstan/src tools/phpstan/tests tools/phpstan/extension.neon tools/phpstan/README.md docs/rules.md`

### 2. Упростить строковые константы по таблице

Цель: убрать константы, которые существовали только как обход слишком широкого правила.

Что сделать:
- Выполнить строки 8-16 из таблицы замен.
- Не трогать строки из раздела «Что не менять».
- Удалить ставшие неиспользуемыми imports, constants и локальные ignores только в затронутых файлах.
- В отчёте выполнения явно отметить, что удаление правила также удаляет текущую незакоммиченную доработку про `TypedConfig::configName()`. Это не откат чужой работы, а следствие удаления всего правила.

Результат: простые технические строки находятся прямо рядом с местом использования, без лишней прослойки.

Сценарии тестирования:
- Temporal ping по-прежнему возвращает `pong`.
- Typed config DTO по-прежнему возвращают правильные имена секций.
- OpenAPI file/html responses по-прежнему выставляют те же HTTP headers.
- API error interceptor по-прежнему возвращает общий текст для непредвиденной ошибки.

Проверка:
- `composer phpstan`
- `vendor/bin/phpunit -c tools/api-error/phpunit.xml --filter ApiExceptionInterceptorTest`
- `vendor/bin/phpstan analyse -c tools/api-error/phpstan.neon`
- `vendor/bin/phpunit -c tools/openapi/phpunit.xml --filter FileResponseTest`
- `vendor/bin/phpunit --filter 'ConfigShapeTest|SimpleConfigMapperTest|ComplexConfigMapperTest'`

### 3. Финальная проверка и отчётность

Цель: убедиться, что удаление правила не сломало остальные проверки и что список замен выполнен полностью.

Что сделать:
- Запустить общий набор проверок проекта и пакетов, затронутых изменениями.
- Проверить, что нет актуальных `@phpstan-ignore project.magicScalarLiteral` в runtime-коде и документации нового стиля.
- Сохранить отчёт выполнения в `docs/executions/` при запуске через `eda-execute`.

Результат: изменения готовы к ревью, без ссылок на удалённое активное правило.

Сценарии тестирования:
- PHPStan проекта проходит без удалённого правила.
- PHPStan tooling проходит без удалённых тестов.
- API error tooling проходит после inline-сообщения.
- OpenAPI response tests проходят после inline-строк.

Проверка:
- `composer tools:phpstan:qa`
- `vendor/bin/phpstan analyse -c tools/api-error/phpstan.neon`
- `vendor/bin/phpunit -c tools/api-error/phpunit.xml`
- `composer tools:openapi:qa`
- `composer phpstan`
- `composer test`
- `rg -n "@phpstan-ignore project\\.magicScalarLiteral|DisallowMagicScalarLiteralRule|project\\.magicScalarLiteral" app/src tools docs/rules.md docs/code-examples.md`

## Тесты

Стратегия: `after_each_phase`. После удаления правила сразу проверить PHPStan tooling. После упрощения строк проверить затронутые unit-тесты и `composer phpstan`. В конце прогнать общий набор команд из фазы 3.

## Логирование

Стратегия: `debug_precise`. Новых runtime-сценариев нет, поэтому новые логи не добавлять. Существующие сообщения логов не менять, кроме удаления лишней константы для текста fallback-ответа.

## Документация и эксплуатация

- Обновить только актуальные правила и README пакета PHPStan.
- Не переписывать исторические документы.
- После реализации ожидаемо исчезнет quality gate `project.magicScalarLiteral`; это нужно явно отметить в отчёте выполнения.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** проверки для пакета `tools/api-error`, потому что план меняет `ApiExceptionInterceptor`.
- **+ Добавлено:** точный новый текст для `docs/rules.md`, чтобы исполнитель не выбирал между удалением и заменой.
- **+ Добавлено:** явное решение не трогать исторические документы и ограничивать финальный поиск актуальными файлами.
- **~ Изменено:** строки про typed config и `docs/code-examples.md` стали проверками текущего состояния, потому что в рабочем дереве ignore уже удалён.
- **~ Изменено:** `ConfigBootloader` перенесён в раздел «Что не менять», потому что его константы описывают контракт автопоиска.
- **Отклонено:** отдельный runtime-тест для Temporal ping не добавлен; меняется только комментарий, достаточно PHPStan и поиска по удалённому ignore.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-20_17-20_remove-magic-scalar-rule.md`

- [x] Шаг 1: удалить PHPStan-правило
- [x] Шаг 2: упростить строковые константы по таблице
- [x] Шаг 3: финальная проверка и отчётность
