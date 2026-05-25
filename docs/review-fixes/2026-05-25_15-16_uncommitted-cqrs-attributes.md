---
review: docs/reviews/2026-05-24_17-48_uncommitted-cqrs-attributes.md
date: 2026-05-25 15:16
status: done
---

# Фиксы по ревью: Незакоммиченные изменения CQRS

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | CQRS-правило PHPStan не применяется к коду приложения | `phpstan.neon` | контрольный PHPStan-пример с неверным `handler`; `make phpstan`; `make test`; `composer -d tools/cqrs phpstan`; `composer -d tools/cqrs test` | ✓ применено |
| 2 | Реализация добавила публичный общий middleware-механизм сверх плана | — | — | — не править по решению пользователя |
| 3 | `AGENTS.md` изменён вне обоих планов | — | — | — не править по решению пользователя |

## Финальная проверка

- **Контроль CQRS-правила:** `vendor/bin/phpstan analyse -c phpstan.neon <temp-file>` — ✓ ошибка `cqrs.handlerCallableRequired` найдена
- **PHPStan приложения:** `make phpstan` — ✓
- **Тесты приложения:** `make test` — ✓, 123 теста, 473 проверки, 26 предупреждений PHPUnit
- **PHPStan tools/cqrs:** `composer -d tools/cqrs phpstan` — ✓
- **Тесты tools/cqrs:** `composer -d tools/cqrs test` — ✓, 19 тестов, 95 проверок
- **Заметки:** пункты 2 и 3 не применялись по прямому решению пользователя.
