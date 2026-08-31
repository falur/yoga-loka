---
title: Запрет loose comparisons в PHPStan
date: 2026-05-14 16:35
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
  research: —
---

# План реализации

## Задача

Коротко: добавить проектное PHPStan-правило, которое запрещает loose comparisons через `==`, `!=` и `<>` во всех PHP-файлах, попадающих в текущий PHPStan-анализ. Готовый результат: `composer phpstan` падает на `==`, `!=` и `<>`, но не ругается на `===`, `!==`, `<`, `>`, `<=`, `>=` и другие операторы.

## Контекст

В проекте уже есть отдельный Composer-пакет для PHPStan tooling: `tools/phpstan`. Его правила подключаются через `tools/phpstan/extension.neon`, а корневой `phpstan.neon` включает `vendor/yoga-loka/phpstan-rules/extension.neon`.

Существующие правила находятся в `tools/phpstan/src/Rules`, тесты и fixtures находятся в `tools/phpstan/tests/Unit/PHPStan`. Это закреплено в `docs/rules.md`: PHPStan tooling должен жить отдельно от runtime-кода приложения, а его тесты и fixtures должны оставаться внутри пакета.

Пользователь подтвердил подход: не подключать `phpstan/phpstan-strict-rules`, а сделать своё точечное правило. Новые Composer-зависимости не нужны.

`docs/arch.md` в проекте не найден, поэтому архитектурная сверка для этого плана опирается на `docs/rules.md` и фактическую структуру `tools/phpstan`.

Правило технически простое: PHPStan должен обрабатывать AST-узлы `PhpParser\Node\Expr\BinaryOp`. Нарушениями считаются `PhpParser\Node\Expr\BinaryOp\Equal` для `==` и `PhpParser\Node\Expr\BinaryOp\NotEqual` для loose not-equal сравнений `!=` и `<>`.

## Принятые решения

- План короткий: одна фаза, ожидаемый объём 4-6 файлов и меньше 200 строк осмысленного diff. Источник: ответ пользователя `1 - 1`.
- Реализовать своё правило в `tools/phpstan`, без зависимости `phpstan/phpstan-strict-rules`. Источник: ответ пользователя `2 - 1`.
- Запрещать `==`, `!=` и `<>` всегда, независимо от типов операндов. Это правило проекта о стиле и безопасности сравнений, а не попытка доказать конкретную type-safety ошибку.
- Использовать отдельные identifiers для двух операторов: `project.looseEqualForbidden` и `project.looseNotEqualForbidden`, чтобы нарушения можно было точечно искать и, если когда-нибудь понадобится, точечно игнорировать.
- Сообщения ошибок должны прямо называть тип запрещённого сравнения и строгую замену: `===` для `==`, `!==` для `!=` и `<>`.
- Runtime-логирование не добавлять. Для PHPStan tooling диагностикой являются сообщения правила, identifiers и номера строк; отдельные debug-логи внутри правила будут шумом в статическом анализе.

## Целевой алгоритм

1. PHPStan анализирует файл из текущих `paths`.
2. Новый rule получает каждый бинарный оператор сравнения через AST.
3. Если узел является `Equal`, rule возвращает ошибку с identifier `project.looseEqualForbidden`, строкой узла и подсказкой использовать `===`.
4. Если узел является `NotEqual`, rule возвращает ошибку с identifier `project.looseNotEqualForbidden`, строкой узла и подсказкой использовать `!==`. Этот AST-узел покрывает оба loose not-equal синтаксиса: `!=` и `<>`.
5. Для всех остальных бинарных операторов rule возвращает пустой список ошибок.
6. Ошибки попадают в обычный вывод PHPStan и ломают `composer phpstan`.

## Фазы выполнения

### 1. Добавить правило strict comparisons

Цель: включить проектный запрет на `==`, `!=` и `<>` в существующий PHPStan tooling без новых зависимостей.

Что сделать:
- Создать `tools/phpstan/src/Rules/DisallowLooseComparisonRule.php`.
- Реализовать rule на `PhpParser\Node\Expr\BinaryOp`.
- В `processNode` вернуть ошибку только для `BinaryOp\Equal` и `BinaryOp\NotEqual`; считать `BinaryOp\NotEqual` запретом и для `!=`, и для `<>`.
- Зарегистрировать rule в `tools/phpstan/extension.neon` рядом с текущими правилами.
- Добавить fixtures внутри `tools/phpstan/tests/Unit/PHPStan/Fixtures`: отдельный файл с разрешёнными строгими сравнениями и другими бинарными операторами, отдельный файл с запрещёнными `==`, `!=` и `<>`.
- Добавить `tools/phpstan/tests/Unit/PHPStan/DisallowLooseComparisonRuleTest.php`.
- В unit-тесте отдельно проверить identifiers через `gatherAnalyserErrors`, ожидаемые строки ошибок и точное количество ошибок, чтобы исключить дубли на одном AST-узле.
- После реализации и тестов исправить реальные нарушения в `app/src` и `tools/phpstan/src`, если новый rule найдёт их при запуске `composer phpstan`.
- Обновить `docs/rules.md`, добавив правило: сравнения значений пишутся только через `===` и `!==`; `==`, `!=` и `<>` запрещены.

Результат: локальный PHPStan-пакет содержит правило, тесты покрывают разрешённые и запрещённые сравнения, корневой анализ подключает правило через существующий extension config.

Сценарии тестирования:
- `==` внутри обычного выражения даёт `project.looseEqualForbidden`.
- `!=` внутри обычного выражения даёт `project.looseNotEqualForbidden`.
- `<>` внутри обычного выражения даёт `project.looseNotEqualForbidden`.
- Несколько нарушений в одном файле возвращаются отдельными ошибками на ожидаемых строках.
- `===` и `!==` не дают ошибок.
- Остальные бинарные операторы, включая `<`, `>`, `<=`, `>=`, `+` и конкатенацию, не дают ошибок этого правила.

Проверка:
- `composer phpstan-rules:test -- --filter DisallowLooseComparisonRuleTest`
- `composer phpstan-rules:phpstan`
- `composer phpstan`
- `rg -n "DisallowLooseComparisonRule|project\\.loose(Equal|NotEqual)Forbidden" tools/phpstan docs/rules.md`
- `! rg -n --pcre2 '(?<![=!])==(?![=>])|(?<![=!])!=(?!=)|<>' app/src tools/phpstan/src`
- `rg -n "===|!==|==|!=|<>" docs/rules.md`

## Тесты

Стратегия: `after_each_phase`. В единственной фазе сначала реализуется правило и регистрация, затем добавляются fixtures и unit-тесты правила, затем запускаются точечные тесты пакета и общий PHPStan.

Основной уровень тестирования: unit-тест PHPStan rule через `PHPStan\Testing\RuleTestCase`. Тест должен проверять сообщения, identifiers, строки и количество ошибок. Интеграционная проверка: сначала `composer phpstan-rules:phpstan` для изолированной проверки tooling-пакета, затем `composer phpstan`, чтобы подтвердить, что правило подключено к корневому анализу и текущий код проекта не содержит loose comparisons.

## Логирование

Стратегия: `debug_precise`, но для этой задачи отдельные логи не добавляются, потому что правило работает внутри PHPStan и не должно писать runtime/debug output. Точная диагностика обеспечивается сообщениями ошибок, identifiers и строками нарушений.

## Документация и эксплуатация

Обновить только `docs/rules.md`, чтобы зафиксировать новое правило проекта. Команда локальной проверки остаётся прежней: `composer phpstan`.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** явное решение по оператору `<>`: он запрещён как loose not-equal comparison и проверяется тем же identifier, что `!=`.
- **+ Добавлено:** требование проверять identifiers, строки и точное количество ошибок в unit-тесте.
- **+ Добавлено:** негативные сценарии для других бинарных операторов, чтобы правило не шумело на `<`, `>`, `<=`, `>=`, `+` и конкатенации.
- **+ Добавлено:** пометка, что `docs/arch.md` отсутствует и план сверяется по `docs/rules.md` и фактической структуре `tools/phpstan`.
- **~ Изменено:** команда `rg` разделена на поиск класса/identifiers, PCRE-поиск loose comparisons в коде без ложных совпадений по `===` и `!==`, и отдельную проверку документации.
- **~ Изменено:** формулировки сообщений и алгоритма теперь говорят про loose not-equal comparisons, а не только про текстовый оператор `!=`.
- **Отклонено:** отдельный механизм дедупликации в правиле не запланирован заранее; вместо этого тест фиксирует точное количество ошибок, а реализация должна оставаться простой, если PHPStan не даёт дублей для этого AST-узла.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-14_16-44_phpstan-disallow-loose-comparisons.md`

- [x] Шаг 1: Добавить правило strict comparisons
