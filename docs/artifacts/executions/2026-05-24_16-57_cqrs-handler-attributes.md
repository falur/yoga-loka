---
plan: docs/plans/2026-05-24_14-26_cqrs-handler-attributes.md
started: 2026-05-24 16:57
finished: 2026-05-24 17:15
status: done
---

# Журнал: CQRS через атрибуты Handler

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить CQRS PHPStan-правило внутри `tools/cqrs` | `tools/cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php`, `tools/cqrs/extension.neon`, `tools/cqrs/tests/PHPStan/*` | `composer -d tools/cqrs test`, `composer -d tools/cqrs phpstan` | done |
| 2 | Перестроить CQRS-пакет на атрибуты | `tools/cqrs/src/*`, `tools/cqrs/tests/*`, `tools/cqrs/composer.json`, `tools/cqrs/phpstan.neon` | `composer -d tools/cqrs test`, `composer -d tools/cqrs phpstan` | done |
| 3 | Адаптировать приложение и документацию | `tests/Feature/CqrsContainerTest.php`, `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md`, `tools/cqrs/README.md`, `composer.lock` | `composer -d tools/cqrs test`, `composer -d tools/cqrs phpstan`, `make test`, `make phpstan` | done |
| 4 | Финальная проверка | весь изменённый объём | `composer -d tools/cqrs test`, `composer -d tools/cqrs phpstan`, `make test`, `make phpstan` | done |

## Заметки
- Фазы 1 и 2 проверены вместе, потому что PHPStan-правило проверяет новый контракт `dispatch(command/query, handler)`, а старый контракт пакета не позволяет изолированно прогнать правило.
- Для удаления прямой зависимости `nyholm/psr7` добавлен `php-http/discovery`: `spiral/framework` требует provider `psr/http-factory-implementation`, а CQRS-код PSR-7 не использует.
- Контрольный поиск старых классов нашёл только README-описание удаления `AfterCommitActions` и тест, проверяющий отсутствие singleton-регистрации. `nyholm/psr7` отсутствует в `tools/cqrs/composer.json` и не установлен в `tools/cqrs/vendor`.

## Изменения в docs
- `docs/arch.md`: CQRS-потоки и архитектура bus переведены с middleware pipeline на атрибуты `Handler::handle()`.
- `docs/rules.md`: правило про `EntityManager::run()` обновлено под `#[Transactional]`.
- `docs/code-examples.md`: пример вызова QueryBus переведён на `dispatch(query: ..., handler: ...->handle(...))`.
- `tools/cqrs/README.md`: описаны атрибуты, новый контракт dispatch, отсутствие after-commit callbacks и проверки пакета.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer -d tools/cqrs test` | OK, 18 tests, 69 assertions |
| `composer -d tools/cqrs phpstan` | OK, no errors |
| `make test` | OK, 123 tests, 473 assertions, 26 PHPUnit deprecations |
| `make phpstan` | OK, no errors |
