---
date: 2026-05-25 20:14
source: text
status: done
---

# Фикс: запрет SQL текстом и перевод outbox-запросов на Query Builder

## Контекст

Пользователь указал `OutboxEventRepository::findPendingIdsForRelay` и попросил добавить правило:
запросы текстом запрещены, всегда использовать Query Builder.

Учтены `docs/rules.md`, `docs/arch.md` и справка Cycle Query Builder.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Добавлено правило о запрете ручных SQL-строк в `$database->query()` и `$database->execute()` для кода приложения и тестов | Зафиксировать новый проектный стандарт |
| 2 | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `findPendingIdsForRelay()` переведён с raw SQL на Cycle Database Query Builder: `select()`, `where()`, `orderBy()`, `limit()`, `forUpdate()` | Убрать текстовый SQL из репозитория |
| 3 | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `claimForRelay()` теперь использует тот же transaction database при выборке идентификаторов | Не терять контекст транзакции при claim-е |
| 4 | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | Очистка `outbox_events` переведена с `execute('DELETE FROM ...')` на `delete()->run()` | Убрать текстовый SQL из теста |

## Важная деталь

Cycle Database Query Builder поддерживает `FOR UPDATE` через `forUpdate()`, но не даёт отдельного API для `SKIP LOCKED`.
Чтобы выполнить новое правило без raw SQL, запрос переведён на доступный builder API с `forUpdate()`.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `rg` по `app/src` и `tests` на `$database->query()`, `$database->execute()` и SQL heredoc | ✓ | Ручных SQL-вызовов вне миграций не найдено |
| `php -l app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | ✓ | Синтаксис корректен |
| `php -l tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | ✓ | Синтаксис корректен |
| `make test` | ✓ | 149 тестов, 600 assertions, 26 PHPUnit deprecations |
| `make phpstan` | ✓ | Ошибок нет |

## Открытые вопросы

Нет.
