---
date: 2026-05-25 20:23
source: text
status: done
---

# Фикс: общий формат даты для запросов к БД

## Контекст

Пользователь указал, что локальная константа `DATABASE_DATETIME_FORMAT` в одном репозитории не подходит:
формат даты для БД должен быть универсальным.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Shared/Infrastructure/Database/DatabaseDateTimeFormat.php` | Добавлен общий класс с форматом `WITH_MICROSECONDS` | Сделать формат даты для БД единым контрактом |
| 2 | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | Локальная константа удалена, формат берётся из общего `DatabaseDateTimeFormat` | Не держать общий формат внутри частного репозитория |
| 3 | `docs/rules.md` | Правило уточнено: для явного форматирования даты в запросах к БД использовать общий `DatabaseDateTimeFormat`, а не строку или локальную константу | Зафиксировать стандарт проекта |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `rg` по `app/src`, `tests`, `docs` на прямой `format('Y-m-d H:i:s.u')` | ✓ | Прямого вызова нет, осталась только общая константа |
| `php -l app/src/Shared/Infrastructure/Database/DatabaseDateTimeFormat.php` | ✓ | Синтаксис корректен |
| `php -l app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | ✓ | Синтаксис корректен |
| `make test` | ✓ | 149 тестов, 600 assertions, 26 PHPUnit deprecations |
| `make phpstan` | ✓ | Ошибок нет |

## Открытые вопросы

Нет.
