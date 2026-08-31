---
title: PHPStan typed constants rule
date: 2026-05-15 13:53
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: —
---

# PHPStan typed constants rule

## Суть

Исследовали, как добавить проектное PHPStan-правило: все class-like константы должны иметь native type, а константы с native type `array` должны иметь PHPDoc с конкретным типом массива, например `list<class-string<Foo>>` или `array<string, class-string>`. Это нужно, потому что сейчас `composer phpstan` проходит без ошибок, хотя в `app/src` есть untyped array-константы: `DEPENDENCIES`, `BINDINGS`, `SINGLETONS`, `INTERCEPTORS` (`app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php:26`, `app/src/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:25`, `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php:17`, `app/src/Infrastructure/Framework/Bootloader/AppBootloader.php:18`).

Критерий готовности исследования: выбрать, где должно жить правило, какой AST-node проверять, как не дублировать уже существующую проверку PHPDoc generic-типов и какие текущие нарушения правило начнёт ловить.

## Решение

Выбранный вариант: добавить отдельное PHPStan-правило `Tools\PHPStan\Rules\RequireTypedConstantsRule` в локальный пакет `tools/phpstan`, зарегистрировать его в `tools/phpstan/extension.neon`, а проверку PHPDoc array-типов переиспользовать через текущие `FileTypeMapper`, `PhpDocContractTypeCollector` и `TypeContractInspector`.

Архитектурный слой уже есть: корневой проект подключает локальный package `yoga-loka/phpstan-rules` через path repository (`composer.json:37`, `composer.json:49-56`), PHPStan extension регистрирует правила как services с тегом `phpstan.rules.rule` (`tools/phpstan/extension.neon:5-25`), а правила проекта проверяются отдельными командами `composer phpstan` и `composer phpstan-rules:test` (`composer.json:78-86`). Правило проекта прямо фиксирует, что PHPStan tooling живёт в `tools/phpstan`, а его тесты и fixtures остаются внутри этого пакета (`docs/rules.md:24-26`).

| Факт | Источник |
|---|---|
| Проект работает на PHP `>=8.5 <8.6`, значит typed class constants доступны. | `composer.json:12`; https://www.php.net/oop5.constants |
| В lock-файле стоит `phpstan/phpstan 2.1.54`; версию менять не нужно. | `composer.lock:10657-10707` |
| Локальный PHPStan package требует `phpstan/phpstan ^2.1.54`. | `tools/phpstan/composer.json:6-9`; `composer.lock:12649-12689` |
| PHPStan рекомендует custom rule для проектных запретов, которые сам язык допускает. | https://phpstan.org/developing-extensions/rules |
| Custom rule выбирает AST-node через `getNodeType()` и возвращает ошибки через `RuleErrorBuilder`. | https://phpstan.org/developing-extensions/rules |
| Для class constants в `nikic/php-parser` есть `PhpParser\Node\Stmt\ClassConst`, у него есть nullable `$type` и `getDocComment()`. | `vendor/nikic/php-parser/lib/PhpParser/Node/Stmt/ClassConst.php:8-42` |
| PHPStan PHPDoc поддерживает конкретные массивы: `array<Type>`, `array<int, Type>`, `list<Type>`, `non-empty-list<Type>`. | https://phpstan.org/writing-php-code/phpdoc-types |
| В проекте уже запрещён неявный `mixed` в PHPDoc array-контрактах, а также nested arrays и array shapes. | `docs/rules.md:13-22`; `tools/phpstan/src/TypeContracts/TypeContractInspector.php:21-64` |

Новая проверка должна слушать `PhpParser\Node\Stmt\ClassConst::class`, а не `PHPStan\Node\FileNode`: отсутствие типа и наличие PHPDoc находятся прямо на `ClassConst` node (`vendor/nikic/php-parser/lib/PhpParser/Node/Stmt/ClassConst.php:15-16`, `vendor/nikic/php-parser/lib/PhpParser/Node/Stmt/ClassConst.php:41-42`). `FileNode` нужен для проверок уровня файла, как текущее `RequireStrictTypesRule` (`tools/phpstan/src/Rules/RequireStrictTypesRule.php:24-47`), но здесь правило локально к конкретной константе.

Логика выбранного правила:

| Случай | Результат |
|---|---|
| `ClassConst::$type === null` | Ошибка `project.constantTypeRequired`: class-like constants must declare a native type. |
| Native type не `array` | Ошибок от нового правила нет; обычную совместимость значения и native type оставляет PHP/PHPStan. |
| Native type `array`, PHPDoc отсутствует | Ошибка `project.arrayConstantPhpDocRequired`: array constants must declare a precise PHPDoc array type. |
| Native type `array`, PHPDoc есть, но без конкретного generic (`@var array`) | Переиспользовать `TypeContractInspector`: ошибка `project.noImplicitMixedType`. |
| Native type `array`, PHPDoc содержит nested array или shape | Переиспользовать текущие запреты `project.noNestedArrayType` / `project.noArrayShapeType`, потому что правила проекта уже запрещают такие контракты. |

Минимальная ожидаемая форма в приложении:

```php
/** @var list<class-string<BootloaderInterface>> */
protected const array DEPENDENCIES = [
    AnnotatedRoutesBootloader::class,
];
```

Для Spiral bootloader-констант нужно брать точные типы из родительских контрактов. У `Spiral\Boot\Bootloader\Bootloader` уже есть PHPDoc для `BINDINGS`, `SINGLETONS` и `DEPENDENCIES` (`vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:20-33`), поэтому текущие app-константы можно исправлять без догадок. `INTERCEPTORS` у `DomainBootloader` сейчас описан только комментарием и значением (`vendor/spiral/framework/src/Framework/Bootloader/DomainBootloader.php:24-51`), поэтому для него тип надо вывести из `defineInterceptors()` и используемых интерфейсов в том же файле (`vendor/spiral/framework/src/Framework/Bootloader/DomainBootloader.php:7-15`, `vendor/spiral/framework/src/Framework/Bootloader/DomainBootloader.php:35-38`).

Развилки закрыты так:

| Развилка | Варианты | Выбор и причина |
|---|---|---|
| Где реализовывать | PHPStan rule, PHP-CS-Fixer rule, ручное ревью | PHPStan rule: проектные типовые запреты уже живут в `tools/phpstan`, а локальная проверка проекта — `composer phpstan` (`docs/rules.md:24-26`). |
| Один общий `TypeContractRule` или новое правило | Расширить `TypeContractRule` на `ClassConst`; добавить отдельное `RequireTypedConstantsRule` | Новое правило: native type обязателен только для констант, а `TypeContractRule` отвечает за PHPDoc-контракты (`tools/phpstan/src/Rules/TypeContractRule.php:37-87`). PHPDoc-инспектор переиспользуется, чтобы не раздвоить правила generic-массивов. |
| Область проверки | Только app classes; все анализируемые PHPStan paths | Все анализируемые PHPStan paths: корневой PHPStan проверяет `app/src`, package PHPStan проверяет `tools/phpstan/src` (`phpstan.neon:4-10`, `tools/phpstan/phpstan.neon:4-10`). Это совпадает с текущей моделью `composer phpstan`. |
| Global constants | Проверять вместе с class constants; не проверять | Не проверять: PHP manual описывает typed class constants, а не typed global constants; AST-node `ClassConst` покрывает class/interface/trait/enum constants. |

Риск совместимости с vendor-родителями принят как низкий: PHP 8.3+ разрешает typed class constants, а локальная проверка `php -r 'class A { protected const FOO = []; } class B extends A { protected const array FOO = []; }'` в текущем окружении завершилась успешно. При реализации всё равно нужно прогнать полный `composer phpstan`, потому что фактические Spiral overrides и PHPStan reflection могут выявить более узкую проблему в конкретном наследовании.

## Ответы на вопросы

Не требовались.

## Итог

Дальше стоит планировать реализацию отдельного `RequireTypedConstantsRule` в `tools/phpstan/src/Rules` с unit-тестами в `tools/phpstan/tests/Unit/PHPStan`, регистрацией в `tools/phpstan/extension.neon` и исправлением текущих app-констант на native typed constants плюс точные `@var` для массивов. Выбранный подход не требует новых зависимостей и ложится в существующий PHPStan tooling-пакет.
