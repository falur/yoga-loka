---
plan: docs/plans/2026-05-12_14-15_phpstan-forbid-array-contracts-and-mixed.md
started: 2026-05-12 14:41
finished: 2026-05-12 14:57
status: done
---

# Журнал: запрет mixed и сложных массивов в PHPStan

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Подключить проектные PHPStan-правила | `composer.json`, `phpstan.neon`, `tools/phpstan/src/*` | `composer dump-autoload`; `composer phpstan` выявил первичные нарушения в `app/src` | done |
| 2 | Закрыть запреты на nested arrays, tuple и array shapes | `tools/phpstan/src/*`, `tests/Unit/PHPStan/*` | `composer test -- --filter PhpStan` — OK, 2 tests / 3 assertions | done |
| 3 | Исправить текущие нарушения в `app/src` | `LocaleSelector.php`, `RoutesBootloader.php`, `tools/phpstan/stubs/SpiralRoutesBootloader.stub`, `phpstan.neon` | `composer phpstan` — OK; `composer test` — exit 0, есть существующий risky `DemoTest`; `rg ... ignoreErrors` — только константы identifiers | done |
| 4 | Финальная проверка и документация | `docs/rules.md`, `docs/executions/*`, `docs/plans/*` | `composer validate --strict`; `composer phpstan`; `composer test`; `composer cs:fix -- --dry-run --diff` | done |

## Заметки

- PHPStan: `phpstan/phpstan 2.1.54` из `composer.json` и `composer.lock`.
- Место выполнения: текущая ветка `main`, по выбору пользователя.
- Зарегистрированное правило: `Tools\PHPStan\Rules\TypeContractRule`.
- Первичные ошибки новых правил в `app/src`: `RoutesBootloader.php:45` (`project.noMixedType`, `project.noNestedArrayType`), `LocaleSelector.php:19`, `:27`, `:51` (`project.noMixedType`).
- Исправления `app/src`: `LocaleSelector` больше не объявляет `mixed` callback и использует `list<string>`; `fetchLocales()` уточнён как `Generator<int, string, void, void>`; nested PHPDoc снят с `RoutesBootloader::middlewareGroups()`.
- Для PHPStan добавлен stub `tools/phpstan/stubs/SpiralRoutesBootloader.stub`, чтобы уточнить vendor override-контракт Spiral без suppressions и без сложного PHPDoc в приложении.

## Изменения в docs
- Добавить `docs/rules.md`: запретить `mixed`, nested arrays, tuple и array shapes в типовых контрактах; указать разрешённые замены.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer validate --strict` | OK, `./composer.json is valid` |
| `composer phpstan` | OK, no errors |
| `composer test` | Exit 0, `3 tests`, `5 assertions`, есть risky `Tests\Unit\DemoTest::testDemo` из-за неудалённых error/exception handlers |
| `composer cs:fix -- --dry-run --diff` | OK, files changed: none |
