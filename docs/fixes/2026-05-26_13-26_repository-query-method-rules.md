---
date: 2026-05-26 13:26
source: text
status: done
---

# Фикс: правила и выборка Repository

## Контекст

Пользователь указал, что методы Repository не должны принимать параметры, которые не относятся к запросу, и что `OutboxEventRepository::findPendingIdsForRelay()` не должен строить выборку через `$database->select()->from('outbox_events')`, если можно использовать `$this->select()`.

Перед правками учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Добавлены правила про параметры методов Repository и выборки через ORM Select. | Чтобы закрепить запрет на технические параметры в методах Repository и не обходить `$this->select()` без причины. |
| 2 | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | Удалён приватный метод `findPendingIdsForRelay()` с параметром `DatabaseInterface`; выборка pending/publishing событий перенесена в `selectPendingForRelay()` через `$this->select()`. | Чтобы метод принимал только параметры запроса и не строил выборку через `$database->select()->from(...)`. |
| 3 | `docs/reviews/2026-05-26_13-15_outbox-rabbitmq-uncommitted.md` | Удалён незавершённый draft-отчёт из прерванного ревью. | Это был служебный файл от прерванной предыдущей задачи. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make test` | ✓ | 152 теста, 610 assertions, 28 PHPUnit deprecations. |
| `make phpstan` | ✓ | Ошибок нет. |

## Открытые вопросы

Нет
