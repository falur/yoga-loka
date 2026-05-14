---
title: PHPStan require strict_types rule
date: 2026-05-14 13:40
mode: normal
plan_size: short
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
  arch: —
  research: docs/researches/2026-05-14_13-32_phpstan-require-strict-types.md
---

# План реализации

## Задача

Добавить проектное правило PHPStan, которое заставляет `composer phpstan` падать на анализируемых PHP-файлах без `declare(strict_types=1)`.

Готовый результат: правило зарегистрировано в `phpstan.neon`, покрыто unit-тестами через `PHPStan\Testing\RuleTestCase`, текущий проект проходит `composer phpstan`, а тесты правила подтверждают ошибки для отсутствующего или неправильного `declare(strict_types=1)`.

## Контекст

| Факт | Источник |
|---|---|
| В проекте уже есть PHPStan tooling-код в `tools/phpstan/src` и тесты правил в `tests/Unit/PHPStan`. | `tools/phpstan/src/Rules/TypeContractRule.php`, `tests/Unit/PHPStan/TypeContractRuleTest.php` |
| `phpstan.neon` регистрирует правила как services с тегом `phpstan.rules.rule`. | `phpstan.neon` |
| `composer.json` уже содержит dev-autoload namespace `Tools\PHPStan\` для `tools/phpstan/src`. | `composer.json` |
| Установлен `phpstan/phpstan 2.1.54`; новые пакеты не нужны. | `composer.json`, `composer.lock` |
| В установленном PHPStan доступен `PHPStan\Node\FileNode::getNodes()`, а `Rule<FileNode>` подходит для проверки файла целиком. | мета-ревью `gpt-5.5` |
| PHPStan рекомендует `PHPStan\Node\FileNode` для проверок на уровне начала файла, включая пример с `declare(strict_types = 1)`. | `docs/researches/2026-05-14_13-32_phpstan-require-strict-types.md` |
| Core PHPStan проверяет существующий `declare(strict_types=...)`, но не вводит проектную политику “declare обязателен”. | `docs/researches/2026-05-14_13-32_phpstan-require-strict-types.md` |
| Проектные PHPDoc-контракты не должны использовать неявный `mixed`, nested arrays, tuple-типы и array shapes. | `docs/rules.md` |

## Принятые решения

- Реализовать собственное правило PHPStan в `tools/phpstan/src/Rules`, без установки `phpstan-strict-rules` или другого пакета. Источник: research и текущая архитектура tooling-кода.
- Правило должно работать по файлам, которые PHPStan уже анализирует по своей конфигурации и CLI-входу. В классе правила нельзя зашивать директории вроде `app/src`, `tools/phpstan/src`, `tests` или `app/config`. Источник: ответ пользователя от 2026-05-14.
- Использовать `PHPStan\Node\FileNode` как node type правила, потому что это штатный способ PHPStan проверять условия на уровне файла. Источник: research.
- Считать корректным только верхнеуровневый `declare(strict_types=1)` в начале файла. Отсутствие `declare`, `strict_types=0`, `strict_types=2` и другой первый statement перед `declare` должны давать проектную ошибку. В unit-тесте правила ожидается только проектная ошибка; возможные core-ошибки PHPStan проверяются общим запуском `composer phpstan`.
- Identifier проектной ошибки задать как `project.missingStrictTypes`, чтобы её можно было отличить от core identifiers `declareStrictTypes.*`.
- План короткий: одна фаза, ожидаемый объём 7-9 файлов и меньше 400 строк осмысленного diff. Основные файлы: новое правило, тест правила, несколько маленьких fixture-файлов, `phpstan.neon`, `docs/rules.md`. Количество файлов выше обычного ориентира short из-за отдельных fixture-сценариев, но осмысленный diff остаётся локальным и ревьюируемым за один проход.

## Целевой алгоритм

1. PHPStan запускается обычной командой `composer phpstan`.
2. PHPStan сам определяет список анализируемых файлов из `phpstan.neon` и CLI-параметров.
3. Для каждого анализируемого файла PHPStan вызывает проектное правило на `PHPStan\Node\FileNode`.
4. Правило читает верхнеуровневые AST nodes файла из `FileNode`.
5. Если первый верхнеуровневый node является `PhpParser\Node\Stmt\Declare_` и среди declares есть `strict_types=1` как `PhpParser\Node\Scalar\Int_` со значением `1`, правило не возвращает ошибок.
6. Если такого первого declare нет, правило возвращает один `RuleError` с понятным сообщением, identifier `project.missingStrictTypes` и строкой `1`.
7. Правило не пишет runtime-логи и не читает конфиг путей: отладочная точность обеспечивается стабильным identifier, точными fixture-тестами и сообщением, которое называет требуемую строку `declare(strict_types=1)`.

## Фазы выполнения

### 1. Добавить правило strict_types и тесты

Цель: включить проектную проверку обязательного `declare(strict_types=1)` в существующий запуск PHPStan.

Что сделать:
- Создать правило в `tools/phpstan/src/Rules`, например `RequireStrictTypesRule`.
- Реализовать `Rule<FileNode>` с явными imports `PHPStan\Node\FileNode`, `PhpParser\Node\Stmt\Declare_` и `PhpParser\Node\Scalar\Int_`: `getNodeType()` возвращает `FileNode::class`, `processNode()` проверяет только nodes текущего файла и не содержит списков директорий.
- Проверять значение `strict_types` строгим сравнением: declare key равен `strict_types`, value является `Int_`, value равен `1`.
- Вынести проверку declare в простой приватный код без PHPDoc array shapes, tuple-типов, nested arrays и неявного `mixed`.
- Сформировать ошибку через `RuleErrorBuilder` с сообщением о том, что каждый анализируемый PHP-файл должен начинаться с `declare(strict_types=1)`, identifier `project.missingStrictTypes` и явной line attribution `1`.
- Зарегистрировать правило в `phpstan.neon` рядом с `TypeContractRule`.
- Добавить unit-тест, например `RequireStrictTypesRuleTest`, через `PHPStan\Testing\RuleTestCase`.
- Добавить fixtures для сценариев: корректный `declare(strict_types=1)`, отсутствие declare, `declare(strict_types=0)`, `declare(strict_types=2)`, несколько declare без первого `strict_types=1`, declare после namespace/use или другого statement.
- В тесте проверить не только message и line, но и identifier через `gatherAnalyserErrors()`, по образцу `TypeContractRuleTest`.
- Обновить `docs/rules.md` и явно закрепить новую локальную проверку как проектное правило.

Результат: `composer phpstan` падает на анализируемом файле без `declare(strict_types=1)` и остаётся чистым на текущем коде проекта.

Сценарии тестирования:
- Файл с первым statement `declare(strict_types=1)` не даёт ошибок правила.
- Файл без `declare(strict_types=1)` даёт одну ошибку `project.missingStrictTypes`.
- Файл с `declare(strict_types=0)` даёт ошибку `project.missingStrictTypes`.
- Файл с `declare(strict_types=2)` даёт ошибку `project.missingStrictTypes`.
- Файл с несколькими `declare`, где первый верхнеуровневый statement не является `declare(strict_types=1)`, даёт ошибку `project.missingStrictTypes`.
- Файл, где `declare(strict_types=1)` не первый верхнеуровневый statement, даёт ошибку проектного правила в unit-тесте custom rule.
- Ожидаемые строки ошибок в fixtures равны `1`, потому что правило задаёт line attribution `1`.
- В классе правила нет захардкоженных путей анализа.

Проверка:
- `composer test -- --filter RequireStrictTypesRuleTest`
- `composer phpstan`
- `composer test -- --filter PhpStan`
- `rg -n "RequireStrictTypesRule|phpstan.rules.rule" phpstan.neon` подтверждает, что правило зарегистрировано как PHPStan service с тегом rule.
- `rg -n "app/src|app/config|tools/phpstan/src|tests" tools/phpstan/src/Rules/RequireStrictTypesRule.php` не должен находить захардкоженные директории.

## Тесты

Стратегия: `after_each_phase`. В фазе сначала реализуется правило и регистрация, затем добавляются fixture-сценарии и запускаются точечные тесты правила. После этого запускается `composer phpstan`, чтобы подтвердить, что правило не ломает текущие анализируемые файлы и что tooling-код сам проходит проектные PHPStan-правила.

## Логирование

Стратегия: `debug_precise`. Runtime-логирование не добавлять, потому что PHPStan-правило работает внутри статического анализа и не является эксплуатационным кодом приложения. Для точной диагностики использовать стабильный error identifier `project.missingStrictTypes`, точное сообщение ошибки и отдельные fixture-сценарии, которые показывают причину срабатывания.

## Документация и эксплуатация

После реализации оставить единую команду проверки `composer phpstan`. Обновить `docs/rules.md` короткой строкой: все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** явная совместимость с `PHPStan\Node\FileNode::getNodes()` в PHPStan 2.1.54.
- **+ Добавлено:** обязательная проверка error identifier `project.missingStrictTypes` через `gatherAnalyserErrors()`.
- **+ Добавлено:** fixture-сценарии для `strict_types=2`, нескольких `declare` и line attribution.
- **+ Добавлено:** отдельная проверка регистрации правила в `phpstan.neon`.
- **~ Изменено:** обновление `docs/rules.md` стало обязательным шагом, а не условным.
- **~ Изменено:** оценка short-объёма увеличена до 7-9 файлов из-за отдельных маленьких fixtures.
- **~ Изменено:** unit-тест late-declare сценария ожидает только ошибку custom rule; общий `composer phpstan` остаётся интеграционной проверкой core+project rules.
- **Отклонено:** расширение области анализа на весь репозиторий не включено, потому что пользователь подтвердил, что область должна браться из настроек PHPStan.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-14_13-52_phpstan-require-strict-types-rule.md`

- [x] Шаг 1: Добавить правило strict_types и тесты.
