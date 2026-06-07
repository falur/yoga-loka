---
date: 2026-05-26 13:32
source: text
status: done
---

# Фикс: запрет транзакций в Repository

## Контекст

Пользователь указал, что транзакции внутри Repository запрещены, это нужно добавить в правила и исправить везде. Отдельно указан `claimForRelay`: claim-сценарий не должен жить в `OutboxEventRepository`.

Перед правками учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Добавлен прямой запрет на `transaction()`, `begin()`, `commit()`, `rollback()` внутри Repository. | Чтобы граница транзакции оставалась в Handler-е, Application-сценарии или инфраструктурном orchestrator-е. |
| 2 | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | Удалён `claimForRelay()` и зависимость от `DatabaseInterface`; Repository оставлен только с выборкой pending-событий и `save()`. | Чтобы Repository отвечал только за доступ к данным, без сценария claim-а и транзакции. |
| 3 | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php` | Короткая транзакция claim-а перенесена в private-метод relay. | Claim — часть сценария relay: выбрать события, перевести в `publishing`, сохранить и отпустить блокировки перед publish. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make test` | ✓ | 152 теста, 610 assertions, 28 PHPUnit deprecations. |
| `make phpstan` | ✓ | Ошибок нет. |
| `find app/src/Modules app/src/Shared -path '*Repository*.php' -type f -print \| sort \| xargs rg -n -- "transaction\(|begin\(|commit\(|rollback\(" \|\| true` | ✓ | Транзакций в Repository не найдено. |

## Открытые вопросы

Нет
