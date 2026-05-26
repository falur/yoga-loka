---
plan: docs/plans/2026-05-25_19-32_public-packages-rename-readme-i18n.md
started: 2026-05-25 19:48
finished: 2026-05-25 20:50
status: done
---

# Журнал: Публичные Composer-пакеты вместо tools

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Перенос директорий и смена публичных имён | `.gitignore`, `composer.json`, `composer.lock`, `phpstan.neon`, `packages/*`, `app/src`, `tests`, `docs/rules.md`, `docs/code-examples.md`, `docs/arch.md` | `composer validate --strict --no-interaction`; `composer install`; `composer dump-autoload`; `composer -d packages/* validate --strict --no-interaction`; `composer -d packages/* install`; `rg` старых ссылок | done |
| 2 | Удаление проектных привязок из package-кода | `packages/*/tests/Portability/PackagePortabilityTest.php`, `packages/spiral-openapi/src/Parser/PhpAstParser.php`, `packages/spiral-openapi/tests/PHPStan/*`, PHPStan rule identifiers | `composer -d packages/* test`; `composer -d packages/* phpstan`; `rg` по package-коду без README | done |
| 3 | Мультиязычность пользовательских текстов без перевода исключений | `packages/spiral-api-errors/locale/*`, `packages/spiral-openapi/locale/*`, `packages/spiral-api-errors/tests/*`, `packages/spiral-openapi/tests/*`, `packages/spiral-cqrs/tests/*`, `packages/spiral-cqrs/src/*`, `packages/spiral-openapi/src/*` | `composer -d packages/spiral-api-errors test`; `composer -d packages/spiral-openapi test`; `composer -d packages/spiral-cqrs test`; `composer -d packages/phpstan-strict-rules test`; `composer -d packages/spiral-api-errors phpstan`; `composer -d packages/spiral-openapi phpstan`; `composer -d packages/spiral-cqrs phpstan`; `composer -d packages/phpstan-strict-rules phpstan`; `rg` старых ключей переводов | done |
| 4 | Подключение строгих PHPStan-правил ко всем пакетам | `packages/*/composer.json`, `packages/*/composer.lock`, `packages/*/phpstan.neon`, `phpstan.neon`, `packages/phpstan-strict-rules/src/Rules/RequireNamedArgumentsRule.php`, `packages/spiral-openapi/src/Parser/PhpAstParser.php`, `packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php` | `composer -d packages/* install`; `composer -d packages/* phpstan`; `composer -d packages/spiral-openapi test`; `rg` старых путей в package-конфигах | done |
| 5 | README и MIT-лицензии публичных пакетов | `packages/*/README.md`, `packages/*/LICENSE` | `composer -d packages/* validate --strict --no-interaction`; `find packages -maxdepth 2 -name LICENSE -print`; `rg` старых внутренних имён в README | done |
| 6 | Корневое приложение, документация и полный набор проверок | `app/src`, `tests`, `composer.json`, `composer.lock`, `phpstan.neon`, `docs/arch.md`, `docs/code-examples.md`, `docs/rules.md` | `make composer-install`; `composer dump-autoload` в Docker; `composer -d packages/* test`; `composer -d packages/* phpstan`; `make phpstan`; `make test`; финальные `rg` | done |

## Заметки

- Корневой Composer-команды выполнены в Docker, потому что на хосте нет PHP-расширения `redis`.
- `composer validate --strict` падал на точных версиях зависимостей. Ограничения заменены на совместимые диапазоны, lock-файлы обновлены без ручной правки.
- PHPStan не принимает `_` в error identifiers. Вместо запланированных `gian_tiaga.*` для PHPStan identifiers используются допустимые package-neutral варианты `gianTiaga.phpstanStrictRules.*`, `gianTiaga.spiralCqrs.*`, `gianTiaga.spiralOpenApi.*`.
- Во время фазы 3 пользователь изменил решение: exception-сообщения не переводятся и остаются на русском. Мультиязычность сохраняется только для пользовательских ответов и OpenAPI-описаний.
- После изменения локального PHPStan extension у `spiral-cqrs`, `spiral-api-errors` и `spiral-openapi` очищен package-local result cache PHPStan, затем проверки прошли без ошибок.
- `make test` прошёл успешно, но PHPUnit сообщил 26 deprecation-предупреждений.

## Изменения в docs

- `docs/rules.md` обновлён с `tools/*` на `packages/*`.
- `docs/arch.md` обновлён под `packages/*`, новые Composer-имена, namespaces и ключи переводов.
- `docs/code-examples.md` обновлён под новые namespaces пакетов.

## Финальная проверка

- `make composer-install` — ok.
- `composer dump-autoload` в Docker — ok.
- `composer -d packages/spiral-cqrs test` — ok.
- `composer -d packages/spiral-api-errors test` — ok.
- `composer -d packages/spiral-openapi test` — ok.
- `composer -d packages/phpstan-strict-rules test` — ok.
- `composer -d packages/spiral-cqrs phpstan` — ok.
- `composer -d packages/spiral-api-errors phpstan` — ok.
- `composer -d packages/spiral-openapi phpstan` — ok.
- `composer -d packages/phpstan-strict-rules phpstan` — ok.
- `make phpstan` — ok.
- `make test` — ok, 123 теста, 473 assertion, 26 PHPUnit deprecations.
- `rg` старых `tools`, `Tools\\`, `vendor/yoga-loka`, `yoga-loka/*`, `yoga_loka.*` по актуальным app/tests/docs/packages — совпадений нет.
