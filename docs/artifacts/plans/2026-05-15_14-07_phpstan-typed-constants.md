---
title: PHPStan typed constants rule
date: 2026-05-15 14:07
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
  arch: —
  research: docs/researches/2026-05-15_13-53_phpstan-typed-constants.md
---

# План реализации

## Задача

Добавить проектное PHPStan-правило, которое требует native type у всех class-like констант. Для констант с native type `array` правило требует точный PHPDoc `@var` с типом массива и переиспользует текущие запреты проекта на неявный `mixed`, вложенные массивы и array shapes.

Готовый результат: `composer phpstan` ловит нетипизированные class-like константы и неточные array-константы, тесты локального PHPStan-пакета покрывают новые ошибки, текущие константы приложения приведены к новому контракту.

## Контекст

| Факт | Значение для реализации |
|---|---|
| PHPStan tooling живёт в `tools/phpstan`, а его тесты и fixtures остаются внутри этого пакета. | Новое правило, тесты и fixtures размещаются только в `tools/phpstan`. |
| Корневой проект подключает `yoga-loka/phpstan-rules` как path repository. | Новое правило попадает в корневой `composer phpstan` через существующий локальный пакет. |
| Правила регистрируются в `tools/phpstan/extension.neon` services с тегом `phpstan.rules.rule`. | Регистрация нового правила делается в этом файле. |
| В lock-файле уже стоит `phpstan/phpstan 2.1.54`, а `tools/phpstan/composer.json` требует `^2.1.54`. | Новые зависимости и обновление версий не нужны. |
| AST-node class-like констант: `PhpParser\Node\Stmt\ClassConst`; у него есть nullable `$type` и `getDocComment()`. | Новое правило слушает `ClassConst::class`, а не `FileNode` и не общий `Node::class`. |
| `TypeContractInspector` уже выдаёт нарушения `project.noImplicitMixedType`, `project.noNestedArrayType`, `project.noArrayShapeType`. | Проверка PHPDoc array-типа должна вызвать существующий инспектор, чтобы не дублировать правила типовых контрактов. |
| В `app/src/Infrastructure/Framework/Bootloader` есть текущие нарушения: `DEPENDENCIES`, `BINDINGS`, `SINGLETONS`, `INTERCEPTORS`. | Реализация включает исправление этих констант на native typed constants и точные `@var`. |
| В `tools/phpstan/src` есть 11 текущих class-like констант без native type. | Реализация включает исправление самого PHPStan tooling-пакета, иначе `composer phpstan-rules:phpstan` упадёт после регистрации нового правила. |
| Native `class-string` в PHP не существует; `class-string<T>` является PHPDoc-типом поверх native `string`. | Тесты используют native `string` с PHPDoc `@var class-string<...>`, а не невалидный native type. |

## Принятые решения

- Добавить отдельное правило `Tools\PHPStan\Rules\RequireTypedConstantsRule` в `tools/phpstan`. Источник: ответ пользователя «проверку добавить» после рекомендации из research.
- Не расширять `TypeContractRule`: новое правило отвечает за обязательный native type у констант, а `TypeContractRule` остаётся правилом для общих PHPDoc-контрактов.
- Проверять все class-like константы, которые видит PHPStan: в class, interface, trait и enum. Глобальные constants не входят в задачу, потому что typed constants относятся к class-like declarations.
- Для native type `array` требовать PHPDoc `@var` на том же `ClassConst` node. Отсутствие PHPDoc даёт отдельную ошибку нового правила, а неточный PHPDoc проверяется через текущий `TypeContractInspector`.
- Для nullable и union type считать константу array-константой, когда один из native type arms равен `array`, например `?array` или `array|string`.
- Обычный docblock без `@var` не закрывает требование для array-константы. Такая константа получает ошибку `project.arrayConstantPhpDocRequired`.
- Multi-const declaration с одним native type и одним docblock проверяется как единая declaration; unit-тест должен зафиксировать строку и поведение для `public const array A = [], B = [];`.
- Новые зависимости не добавлять. Используется текущий `phpstan/phpstan 2.1.54` из lock-файла.
- Ошибки нового правила сделать точными и стабильными: `project.constantTypeRequired` для отсутствующего native type и `project.arrayConstantPhpDocRequired` для array-константы без PHPDoc.
- Стратегия тестов: после каждой фазы добавлять или обновлять тесты и запускать проверки этой фазы.
- Стратегия логирования: runtime-логи не добавлять, потому что PHPStan rule не является runtime-кодом приложения. Точная диагностика обеспечивается error identifiers, сообщениями и строками в PHPStan-ошибках.

## Целевой алгоритм

1. PHPStan встречает class-like constant declaration и передаёт `PhpParser\Node\Stmt\ClassConst` в `RequireTypedConstantsRule`.
2. Правило проверяет native type у declaration.
3. Если native type отсутствует, правило возвращает ошибку `project.constantTypeRequired` на строке константы.
4. Правило разворачивает native type declaration: `array`, `?array` и union-типы с arm `array` считаются array-константами.
5. Для native type без `array` правило завершает проверку declaration без ошибки.
6. Для array-константы правило читает PHPDoc у той же declaration.
7. Для отсутствующего PHPDoc или PHPDoc без `@var` правило возвращает ошибку `project.arrayConstantPhpDocRequired` на строке declaration или docblock.
8. Для PHPDoc с `@var` правило разбирает docblock через `FileTypeMapper`, собирает типы через `PhpDocContractTypeCollector` и передаёт их в `TypeContractInspector`.
9. Нарушения инспектора превращаются в PHPStan-ошибки с уже существующими identifiers.
10. PHPStan выводит точные identifiers, сообщения и строки, а `composer phpstan` падает при любом нарушении.

## Фазы выполнения

### 1. Реализовать и зарегистрировать правило

Цель: добавить PHPStan rule, которое ловит отсутствующий native type у class-like констант и отсутствие PHPDoc у array-констант.

Что сделать:
- Создать `tools/phpstan/src/Rules/RequireTypedConstantsRule.php`.
- Реализовать `Rule<ClassConst>` с `getNodeType(): ClassConst::class`.
- Добавить константы сообщений и identifiers для двух новых ошибок.
- Для отсутствующего `$node->type` вернуть ошибку `project.constantTypeRequired`.
- Для native type declaration с `array`, `?array` или union arm `array` включить проверку PHPDoc.
- Для array-константы без `getDocComment()` вернуть ошибку `project.arrayConstantPhpDocRequired`.
- Для array-константы с PHPDoc без `@var` вернуть ошибку `project.arrayConstantPhpDocRequired`.
- Для array-константы с `@var` разобрать docblock через `FileTypeMapper`, `PhpDocContractTypeCollector` и `TypeContractInspector`.
- Сохранять строки ошибок на строке declaration или docblock так же, как в текущих правилах.
- Зарегистрировать правило в `tools/phpstan/extension.neon` с тегом `phpstan.rules.rule`.

Результат: локальный PHPStan extension содержит новое правило и может построить его через DI-контейнер PHPStan.

Сценарии тестирования:
- Константа без native type отклоняется.
- Константа `array` без PHPDoc отклоняется.
- Константа `array` с обычным docblock без `@var` отклоняется.
- Константа `?array` и константа с union type `array|string` требуют точный `@var`.
- Константа `array` с `@var array` отклоняется текущим identifier `project.noImplicitMixedType`.
- Константа `array` с nested array или array shape отклоняется текущими identifiers инспектора.
- Константа с native type `string`, `int`, `bool` или union без `array` проходит новое правило.
- Константа с native `string` и PHPDoc `@var class-string<...>` проходит новое правило.
- Class-like константы в class, interface, trait и enum покрыты fixtures.
- Несколько констант в одном `ClassConst` declaration покрыты fixture.

Проверка:
- Добавить unit-test `tools/phpstan/tests/Unit/PHPStan/RequireTypedConstantsRuleTest.php`.
- Добавить fixtures рядом с существующими fixtures в `tools/phpstan/tests/Unit/PHPStan/Fixtures`.
- Запустить `composer phpstan-rules:test`.

### 2. Привести текущий код приложения к новому контракту

Цель: убрать текущие нарушения, чтобы новое правило проходило на `app/src` и `tools/phpstan/src`.

Что сделать:
- В `tools/phpstan/src/TypeContracts/TypeContractViolation.php` добавить native type `string` ко всем public constants.
- В `tools/phpstan/src/Rules/DisallowLooseComparisonRule.php` добавить native type `string` ко всем private message и identifier constants.
- В `tools/phpstan/src/Rules/RequireNamedArgumentsRule.php` добавить native type `string` к private constants.
- В `tools/phpstan/src/Rules/RequireStrictTypesRule.php` добавить native type `string` к private constants.
- В `AppBootloader` добавить native type `array` для `SINGLETONS` и `INTERCEPTORS`.
- В `AppBootloader` добавить точный `@var` для `SINGLETONS` по контракту Spiral bootloader bindings.
- В `AppBootloader` добавить точный `@var` для `INTERCEPTORS` по `CoreInterceptorInterface|InterceptorInterface|class-string<CoreInterceptorInterface>|class-string<InterceptorInterface>`.
- В `ConfigBootloader` добавить native type `array` и точный `@var` для `SINGLETONS`.
- В `ExceptionHandlerBootloader` добавить native type `array` и точный `@var` для `BINDINGS`.
- В `RoutesBootloader` добавить native type `array` и точный `@var` для `DEPENDENCIES`.
- Сверить типы `BINDINGS`, `SINGLETONS` и `DEPENDENCIES` с PHPDoc родительского `Spiral\Boot\Bootloader\Bootloader`.
- Сверить тип `INTERCEPTORS` с `DomainBootloader::defineInterceptors()` и используемыми интерфейсами.

Результат: все текущие class-like константы приложения и локального PHPStan-пакета имеют native type, а array-константы имеют точный PHPDoc.

Сценарии тестирования:
- Корневой `composer phpstan` проходит на `app/src` и `tools/phpstan/src`.
- `composer phpstan-rules:phpstan` проходит на собственном коде `tools/phpstan/src`.
- Константы bootloader сохраняют совместимость с родительскими Spiral-контрактами.
- Новое правило не создаёт лишних ошибок для typed non-array constants.

Проверка:
- Запустить `composer phpstan`.
- Запустить `composer phpstan-rules:phpstan`.
- Запустить `composer phpstan-rules:test`.

### 3. Закрепить итог полным прогоном

Цель: проверить, что правило работает в пакете и в корневом проекте вместе с остальными проектными правилами.

Что сделать:
- Добавить в `docs/rules.md` правило проекта: все class-like константы должны иметь native type, а array-константы должны иметь точный PHPDoc `@var`.
- Запустить `composer phpstan-rules:test`.
- Запустить `composer phpstan-rules:phpstan`.
- Запустить `composer phpstan`.
- Запустить `composer test`.
- Проверить, что новые messages и identifiers стабильны и совпадают с unit-тестами.

Результат: реализация готова к ревью, а проверка покрывает правило, локальный PHPStan-пакет и приложение.

Сценарии тестирования:
- Unit-тесты нового правила проходят.
- Статический анализ локального PHPStan-пакета проходит.
- Корневой статический анализ проходит.
- Общий test suite проходит.

Проверка:
- Все команды из фазы завершены с кодом 0.

## Тесты

Стратегия: `after_each_phase`. После фазы 1 пишутся fixtures и unit-тесты нового PHPStan rule, затем запускается `composer phpstan-rules:test`. После фазы 2 запускаются `composer phpstan` и `composer phpstan-rules:phpstan`, чтобы поймать нарушения в `app/src` и `tools/phpstan/src`. После фазы 3 выполняется полный набор проверок.

Минимальный набор тестов:
- allowed fixture с typed non-array constants и typed array constants с точным `@var`;
- forbidden fixture без native type;
- forbidden fixture с `const array` без PHPDoc;
- forbidden fixture с `const array` и docblock без `@var`;
- forbidden fixture с `?array` или `array|string` без точного `@var`;
- forbidden fixture с `@var array`;
- forbidden fixture с nested array и array shape, чтобы подтвердить переиспользование `TypeContractInspector`.
- fixtures для class, interface, trait и enum;
- fixture для multi-const declaration.

## Логирование

Стратегия: `debug_precise`. В runtime-код приложения логи не добавляются. Для статического правила роль точной диагностики выполняют PHPStan identifiers, сообщения и строки:

- `project.constantTypeRequired` сообщает, что class-like constant должна иметь native type;
- `project.arrayConstantPhpDocRequired` сообщает, что array-константа должна иметь точный PHPDoc `@var`;
- ошибки `project.noImplicitMixedType`, `project.noNestedArrayType`, `project.noArrayShapeType` остаются источником диагностики для содержимого PHPDoc.

## Документация и эксплуатация

- Обновить `docs/rules.md`, чтобы источник правил отражал новый quality gate для class-like constants.
- Пользовательскую документацию приложения не обновлять: правило является внутренним quality gate.
- В кодовых комментариях использовать русский язык только там, где комментарий действительно помогает понять неочевидную проверку.
- Для релиза важно, что после мержа `composer phpstan` станет строже и начнёт блокировать новые class-like константы без native type.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** исправление 11 существующих untyped constants в `tools/phpstan/src`, без которого упадёт `composer phpstan-rules:phpstan`.
- **+ Добавлено:** проверка `?array` и union native types с arm `array`.
- **+ Добавлено:** требование именно `@var` для array-констант, включая случай обычного docblock без `@var`.
- **+ Добавлено:** тестовые сценарии для class, interface, trait, enum и multi-const declaration.
- **+ Добавлено:** обновление `docs/rules.md` как часть будущей реализации.
- **~ Изменено:** тестовый сценарий с невалидным native `class-string` заменён на native `string` с PHPDoc `@var class-string<...>`.
- **Отклонено:** отдельный архитектурный файл не добавляется в этот план, потому что `docs/arch.md` отсутствует и источник архитектуры для этой локальной правки уже зафиксирован как `—`.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-15_14-17_phpstan-typed-constants.md`

- [x] Шаг 1: Реализовать и зарегистрировать правило
- [x] Шаг 2: Привести текущий код приложения к новому контракту
- [x] Шаг 3: Закрепить итог полным прогоном
