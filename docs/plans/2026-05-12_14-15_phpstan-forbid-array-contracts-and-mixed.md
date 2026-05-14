---
title: Запретить mixed и сложные массивы правилами PHPStan
date: 2026-05-12 14:15
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex-partial
  - gpt-5.5-partial
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: —
  arch: —
  research: —
---

# План реализации

## Задача

Добавить проектные правила PHPStan, которые запрещают в проверяемых типовых контрактах:

- `mixed` в любом месте типа;
- вложенные массивы в типах;
- tuple-типы;
- array shapes.

Готовый результат: `composer phpstan` падает на таких типах в коде проекта, правила покрыты тестами, существующие нарушения в `app/src` исправлены без отключения новых правил.

## Контекст

- В проекте уже установлен `phpstan/phpstan 2.1.54`, версия зафиксирована в `composer.json` и `composer.lock`.
- Текущий `phpstan.neon` анализирует `app/src` на `level: max`, под PHP `8.5.6`, с загрузкой `vendor/autoload.php`.
- Отдельных проектных PHPStan-правил сейчас нет.
- `docs/rules.md` и `docs/arch.md` отсутствуют, дополнительных проектных ограничений для планирования нет.
- В текущем коде уже видны типы, которые попадут под новые правила: `mixed` в callback `LocaleSelector`, PHPDoc `string[]`, PHPDoc `array<string, array<...>>`.
- В коде Spiral есть override-методы, которые по framework-контракту возвращают обычные массивы конфигурации. Новые правила должны запрещать сложные массивы именно в типовых декларациях и PHPDoc, а не обычные array literals в теле методов.
- PHPStan `Rule` и `RuleErrorBuilder` доступны как публичный API в установленном phar. `TypeTraverser`, `MixedType`, `ArrayType` и `ConstantArrayType` также доступны и подходят для обхода уже разрешённых PHPStan типов.
- `PHPStan\Type\FileTypeMapper` и `PHPStan\PhpDoc\ResolvedPhpDocBlock` доступны в установленной версии PHPStan. Через них можно получать типы из `@var`, `@param`, `@param-out`, `@return`, `@throws`, `@template`, `@phpstan-type`, `@property` и `@method` без regex-разбора комментариев.
- Для тестов доступен `PHPStan\Testing\RuleTestCase` из установленного `phpstan/phpstan`.

## Принятые решения

- Новые зависимости не добавлять. Правила реализуются на уже установленном `phpstan/phpstan 2.1.54`; источник версии — текущие `composer.json` и `composer.lock`.
- Разместить код правил отдельно от приложения, в `tools/phpstan/src`, с namespace `Tools\PHPStan`. Это tooling-код, он не должен смешиваться с runtime-кодом приложения.
- Подключить `Tools\PHPStan\` через `autoload-dev` в `composer.json` и выполнить `composer dump-autoload` в ходе реализации.
- Добавить `tools/phpstan/src` в `phpstan.neon` рядом с `app/src`, чтобы сами правила тоже проходили проектный PHPStan.
- Код правил в `tools/phpstan/src` должен проходить эти же запреты без suppressions. В реализации использовать именованные маленькие классы или `list<T>` вместо array shapes, не использовать `mixed` в PHPDoc и сигнатурах.
- Проверять только типовые контракты: нативные типы, PHPDoc-типы параметров, return, property, `@var`, `@param-out`, `@throws`, `@template`, `@phpstan-type`, `@property` и `@method`, которые PHPStan отдаёт как `Type`.
- Не запрещать обычные массивы значений в теле методов. Запрет касается формы типов, а не самого использования array literal как структуры данных.
- Считать `ConstantArrayType` запрещённым типом в типовых контрактах. Так PHPStan представляет array shapes и tuple-типы после разбора PHPDoc.
- Считать вложенным массивом тип, где внутри значения массива находится другой `ArrayType` или `ConstantArrayType`. Первый уровень `array<int, Foo>` или `list<Foo>` разрешён, если значение не является массивом, shape, tuple или `mixed`.
- Запрещать `MixedType` в любом месте проверяемого типа, включая явный `mixed`, `array<string, mixed>`, callable-параметры и вложенные generic-типы. Неявные missing-type ошибки дополнительно закрывает текущий PHPStan `level: max`.
- Использовать стабильные идентификаторы ошибок:
  - `project.noMixedType`;
  - `project.noNestedArrayType`;
  - `project.noArrayShapeType`.
- Стратегия тестов: `after_each_phase`, по настройке `docs/settings.yaml`.
- Стратегия логирования: `debug_precise`, но runtime-логи приложения не добавляются. Для этой задачи логированием считается точная запись команд, первичных ошибок PHPStan и финальных проверок в журнале выполнения.

## Целевой алгоритм

1. PHPStan загружает `vendor/autoload.php`, затем классы `Tools\PHPStan` из dev-autoload.
2. `phpstan.neon` регистрирует проектные правила через сервисы с тегом `phpstan.rules.rule`.
3. Во время анализа PHPStan передаёт правилам узлы с декларациями функций, методов, свойств и PHPDoc-уточнениями.
4. Для сигнатур правило получает уже разрешённый PHPStan `Type` из reflection или virtual node.
5. Для PHPDoc-тегов правило получает `ResolvedPhpDocBlock` через `FileTypeMapper` и извлекает из него типы всех поддержанных tags.
6. Общий инспектор типов рекурсивно обходит каждый `Type` через `TypeTraverser`.
7. Если инспектор находит `MixedType`, он возвращает ошибку `project.noMixedType`.
8. Если инспектор находит `ConstantArrayType`, он возвращает ошибку `project.noArrayShapeType`.
9. Если инспектор находит массив, внутри value-type которого снова есть массив или shape, он возвращает ошибку `project.noNestedArrayType`.
10. PHPStan печатает ошибку с понятным сообщением и местом в исходном файле.
11. Разработчик заменяет запрещённый тип на именованный value object, DTO, enum, коллекцию или более точный скалярный/object-тип.
12. Финальное состояние подтверждается чистым `composer phpstan`, тестами правил и общими проверками проекта.

## Фазы выполнения

### 1. Подключить проектные PHPStan-правила

Цель: создать минимальную инфраструктуру, в которой PHPStan видит и запускает проектные правила.

Что сделать:

- Добавить dev-autoload namespace `Tools\PHPStan\` на каталог `tools/phpstan/src`.
- Создать каталог `tools/phpstan/src/Rules` и общий инспектор типов в `tools/phpstan/src/TypeContractInspector.php`.
- Создать правила для функций, методов, свойств и `@var` PHPDoc-уточнений на основе доступных PHPStan node/reflection API.
- Добавить reader PHPDoc-контрактов на основе `FileTypeMapper` и `ResolvedPhpDocBlock`; он возвращает только список найденных `Type`, без array shapes в собственных PHPDoc.
- Зарегистрировать правила в `phpstan.neon` как сервисы с тегом `phpstan.rules.rule`.
- Добавить `tools/phpstan/src` в `parameters.paths` PHPStan.
- Выполнить `composer dump-autoload`.
- Запустить `composer phpstan` и записать в журнал выполнения первичный список нарушений новых правил.
- После реализации фазы добавить или обновить тесты на то, что PHPStan загружает новые сервисы и выдаёт ошибку на простом `mixed` в сигнатуре.

Результат: PHPStan запускает проектные правила, а первое нарушение `mixed` обнаруживается через новый error identifier.

Сценарии тестирования:

- Правило находит `mixed` в параметре метода.
- Правило находит `mixed` в return PHPDoc.
- Правило получает типы из `ResolvedPhpDocBlock` без regex-разбора комментариев.
- PHPStan config успешно загружает сервисы правил.

Проверка:

- `composer dump-autoload`
- `composer phpstan`
- `composer test -- --filter PhpStan`

### 2. Закрыть запреты на nested arrays, tuple и array shapes

Цель: довести инспектор типов до полного набора запретов из задачи.

Что сделать:

- Реализовать обход вложенных типов через `PHPStan\Type\TypeTraverser`.
- Для `MixedType` возвращать нарушение `project.noMixedType`.
- Для `ConstantArrayType` возвращать нарушение `project.noArrayShapeType` с текстом, что array shapes и tuple-типы запрещены.
- Для `ArrayType` проверять value-type и возвращать `project.noNestedArrayType`, когда внутри value-type есть `ArrayType` или `ConstantArrayType`.
- Добавить тестовые fixtures для разрешённых типов: `array<int, string>`, `list<string>`, `iterable<int, SomeDto>`, именованный DTO вместо shape.
- Добавить тестовые fixtures для запрещённых типов: `array<string, array<int, string>>`, `list<array<string, int>>`, `array{foo: string}`, `array{0: string, 1: int}`, `array<string, mixed>`, `callable(mixed): void`.
- Добавить fixture для `@param-out`, `@throws`, `@template`, `@phpstan-type`, `@property` и `@method`, где запрещённый тип находится не в обычном параметре или return.
- Зафиксировать точные expected errors и identifiers в unit-тестах правил.
- Запустить тесты правил и `composer phpstan`.

Результат: все четыре категории запрета покрыты автоматическими тестами и работают через один общий инспектор.

Сценарии тестирования:

- Одноуровневый массив с конкретным scalar или object value-type разрешён.
- Вложенный массив запрещён.
- Array shape запрещён.
- Tuple запрещён тем же правилом, что и shape.
- `mixed` запрещён на верхнем уровне и внутри generic/callable/array-типа.
- `mixed`, nested array, shape и tuple запрещены внутри `@param-out`, `@throws`, `@template`, `@phpstan-type`, `@property` и `@method`.

Проверка:

- `composer test -- --filter PhpStan`
- `composer phpstan`

### 3. Исправить текущие нарушения в app/src

Цель: привести текущий код приложения к новым правилам без отключений и широких suppressions.

Что сделать:

- Убрать `mixed` из callback в `LocaleSelector`, сохранив фильтрацию локалей безопасной для значений неизвестного типа.
- Заменить PHPDoc `string[]` на разрешённый `list<string>` или `array<int, string>`.
- Убрать nested-array PHPDoc из `RoutesBootloader::middlewareGroups()` и оставить только native `array`, потому что метод реализует framework override-контракт с конфигурационным массивом.
- Проверить остальные PHPDoc-типы в `app/src` и заменить запрещённые формы на разрешённые одноуровневые массивы или именованные классы.
- Не добавлять ignores для новых identifiers в `phpstan.neon`.
- Проверить `tools/phpstan/src` тем же `composer phpstan`; код правил не должен использовать suppressions для собственных identifiers.
- После исправлений фазы запустить PHPStan и тесты.

Результат: текущий `app/src` проходит новые правила без baseline и без suppressions.

Сценарии тестирования:

- Locale filtering сохраняет поведение для строковых и нестроковых значений из catalogue manager.
- `RoutesBootloader` сохраняет ожидаемые middleware-группы для Spiral.
- PHPStan не содержит новых ignoreErrors для `project.noMixedType`, `project.noNestedArrayType`, `project.noArrayShapeType`.
- `tools/phpstan/src` проходит проектный PHPStan без suppressions новых identifiers.

Проверка:

- `composer phpstan`
- `composer test`
- `rg -n "project\\.noMixedType|project\\.noNestedArrayType|project\\.noArrayShapeType|ignoreErrors" phpstan.neon app/src tools/phpstan`

### 4. Финальная проверка и документация

Цель: зафиксировать новые правила как постоянный quality gate проекта.

Что сделать:

- Добавить короткую документацию в `docs/rules.md` или создать файл, если его нет: какие типы запрещены и чем их заменять.
- Зафиксировать в журнале выполнения команды, версии, первичные нарушения, исправления и финальные чистые прогоны.
- Выполнить полный набор проверок проекта.
- Проверить, что `phpstan.neon` не содержит baseline и не отключает новые правила.

Результат: правила описаны, checks воспроизводимы, проект проходит все проверки.

Сценарии тестирования:

- Новый разработчик видит правило в документации и понимает разрешённые замены.
- CI/local quality gate падает на forbidden type fixtures и проходит на проектном коде.

Проверка:

- `composer validate --strict`
- `composer phpstan`
- `composer test`
- `composer cs:fix -- --dry-run --diff`

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы исполнитель добавляет или обновляет тесты, которые соответствуют реализованной части, и запускает их до перехода дальше. Основной уровень тестирования — unit-тесты PHPStan rules через `PHPStan\Testing\RuleTestCase` с отдельными fixture-файлами. После фаз, которые меняют приложение, дополнительно запускаются `composer phpstan` и `composer test`.

## Логирование

Стратегия: `debug_precise`.

Runtime-логи приложения не добавляются, потому что задача меняет статический анализ. В журнале выполнения нужно точно записать:

- версию PHPStan;
- изменённые config/autoload файлы;
- список зарегистрированных правил;
- первичные ошибки новых правил;
- принятые исправления текущего кода;
- финальные команды и их результат.

В журнал не попадают `.env`-значения, секреты и персональные данные.

## Документация и эксплуатация

- Обновить или создать `docs/rules.md` с коротким правилом: запрещены `mixed`, nested arrays, tuple и array shapes в типовых контрактах.
- Указать разрешённые замены: именованные DTO/value object, enum, коллекция, `list<T>` или `array<int|string, T>` с невложенным `T`.
- Для локальной проверки оставить команду `composer phpstan`.
- Для релиза важно, что новые правила становятся частью обязательного quality gate и не имеют baseline.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** явное покрытие PHPDoc-тегов `@param-out`, `@throws`, `@template`, `@phpstan-type`, `@property` и `@method` через `ResolvedPhpDocBlock`.
- **+ Добавлено:** требование реализовать tooling-код без `mixed`, array shapes и suppressions, потому что `tools/phpstan/src` включается в общий PHPStan paths.
- **+ Добавлено:** тестовые fixtures для запрещённых типов внутри неочевидных PHPDoc-контрактов, а не только параметров, return и свойств.
- **~ Изменено:** целевой алгоритм теперь разделяет получение типов из reflection и получение типов из PHPDoc через `FileTypeMapper`.
- **Отклонено:** отключать самопроверку `tools/phpstan/src` не нужно; вместо этого план требует писать код правил так, чтобы он проходил тот же quality gate.
- **Отклонено:** замечания из `gpt-5.3-codex` и `gpt-5.5` приняты только там, где они подтверждены частичным выводом и локальной проверкой API; оба процесса были остановлены после зависания без финального короткого блока.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-12_14-41_phpstan-forbid-array-contracts-and-mixed.md`

- [x] Шаг 1: Подключить проектные PHPStan-правила
- [x] Шаг 2: Закрыть запреты на nested arrays, tuple и array shapes
- [x] Шаг 3: Исправить текущие нарушения в app/src
- [x] Шаг 4: Финальная проверка и документация
