---
title: PHPStan require declare strict_types
date: 2026-05-14 13:32
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: —
---

# PHPStan require declare strict_types

## Суть

Исследовали, как заставить `composer phpstan` падать на PHP-файлах без `declare(strict_types=1)`.

В проекте уже есть собственный PHPStan tooling-слой: `tools/phpstan/src` подключён через Composer dev-autoload, а `phpstan.neon` регистрирует правила сервисами с тегом `phpstan.rules.rule` и анализирует `app/src` вместе с `tools/phpstan/src`. Локальное правило проекта требует запускать проверку через `composer phpstan`.

## Решение

Рекомендованный вариант: добавить собственное PHPStan-правило в `tools/phpstan/src/Rules`, без новых зависимостей.

| Факт | Источник |
|---|---|
| В проекте закреплён `phpstan/phpstan 2.1.54`. | `composer.json`, `composer.lock` |
| `phpstan.neon` уже подключает проектные rules как services с тегом `phpstan.rules.rule`. | `phpstan.neon` |
| Официальная документация PHPStan рекомендует custom rules для проектных запретов, которые язык сам не считает ошибкой. | https://phpstan.org/developing-extensions/rules |
| Для проверок на уровне всего файла PHPStan предлагает virtual node `PHPStan\Node\FileNode`, включая пример про `declare(strict_types = 1)`. | https://phpstan.org/developing-extensions/rules |
| Core PHPStan имеет `DeclareStrictTypesRule`, но публичные identifiers для него — `declareStrictTypes.value` и `declareStrictTypes.notFirst`; missing declaration среди них нет. | https://phpstan.org/error-identifiers, https://phpstan.org/error-identifiers/declareStrictTypes.value, https://phpstan.org/error-identifiers/declareStrictTypes.notFirst |
| `phpstan-strict-rules` перечисляет дополнительные строгие правила, но правила “require strict_types declaration” в списке нет. | https://github.com/phpstan/phpstan-strict-rules |
| `strict_types` работает per-file и влияет на scalar type declarations в вызывающем файле. | https://www.php.net/manual/en/language.types.declarations.php |

Архитектурно это tooling-код, не runtime-код приложения. Правило должно жить рядом с текущим `TypeContractRule`, чтобы `composer phpstan` оставался единой точкой качества.

Предлагаемый смысл правила:

1. `getNodeType()` возвращает `PHPStan\Node\FileNode::class`.
2. `processNode()` проверяет верхнеуровневые statements файла.
3. Файл проходит, если первый meaningful statement — `PhpParser\Node\Stmt\Declare_` со `strict_types=1`.
4. Файл падает, если `declare` отсутствует, стоит не первым или указано `strict_types=0`.
5. Ошибка получает проектный identifier, например `project.missingStrictTypes`.

Тонкость: PHPStan core уже ловит “не первым” и невалидное значение для существующего `declare`, поэтому наше правило может сфокусироваться на отсутствии и `strict_types=0`. Но для понятной проектной политики лучше формулировать ошибку как “Every analysed PHP file must start with declare(strict_types=1).” и покрыть тестом все варианты.

Область проверки должна совпадать с `phpstan.neon` `parameters.paths`. Сейчас это `app/src` и `tools/phpstan/src`; `app/config`, `tests` и fixtures туда не входят, даже если в них есть PHP-файлы. Если нужно требовать `declare` и там, надо отдельно расширять `paths`, но это уже отдельное решение, потому что увеличит область статического анализа.

## Ответы на вопросы

Не требовались. Существенное решение вынесено в обсуждение: добавлять своё правило без новой зависимости или расширять область задачи сторонними инструментами.

## Итог

Дальше лучше идти через собственное PHPStan-правило на `FileNode`, без установки `phpstan-strict-rules`. Это точечно решает нужный запрет, ложится в существующий tooling-слой и не меняет runtime-зависимости.
