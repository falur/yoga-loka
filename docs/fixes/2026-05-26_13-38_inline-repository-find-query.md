---
date: 2026-05-26 13:38
source: text
status: done
---

# Фикс: запрос outbox relay прямо в find-методе

## Контекст

Пользователь указал, что приватный метод `selectPendingForRelay()` не нужен: его логика должна быть прямо в `findPendingForRelay()`.

Перед правкой учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

- В `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` удалён приватный метод `selectPendingForRelay()`.
- Логика выборки pending/publishing событий перенесена напрямую в публичный метод `findPendingForRelay()`.
- Поведение выборки не менялось: фильтр по статусам и `available_at`, сортировка по `id`, лимит и `forUpdate()` сохранены.

## Тесты и проверки

- `make test` — успешно: 152 теста, 610 assertions, 28 PHPUnit deprecations.
- `make phpstan` — успешно, ошибок нет.

## Открытые вопросы

Нет.
