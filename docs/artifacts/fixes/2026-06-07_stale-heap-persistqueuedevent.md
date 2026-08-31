---
date: 2026-06-07
source: text (точечный фикс пре-существующего дефекта в тест-хелпере Outbox-среза)
status: done
---

# Фикс: stale heap в persistQueuedEvent после CAS-перехода publishing -> queued

## Контекст

Пре-существующий дефект в тест-инфраструктуре незакоммиченного Outbox-среза (не
связан с ревью).

Файл `tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php`,
метод `persistQueuedEvent()` создаёт событие, вызывает `markPublishing()`,
делает `persist()` + `run()`, затем `markQueuedIfPublishing()` (сырой DB UPDATE
через репозиторий, минуя ORM) и `findById()`.

`findById()` -> `findByPK()` возвращает закэшированную в identity map (ORM heap)
сущность со статусом `Publishing`, тогда как строка в БД уже `queued`. Из-за
этого падал Feature-тест
`OutboxQueueStatusInterceptorTest::testInterceptorDoesNotRunJobWhenHeadersAndPayloadHaveDifferentOutboxIds`
на `self::assertSame(OutboxEventStatus::Queued, $storedOutboxEvent->status)`
(строка ~281): интерцептор там бросает исключение до мутации, поэтому тест
проверяет статус именно из хелпера.

Учтённые правила/архитектура: `docs/rules.md` (запрет ослабления тестов и
изменения боевого кода без причины, идиоматичность), `docs/arch.md` (outbox
relay делает технические переходы статусов сырым SQL). Боевой код не менялся.

Идиоматичный для Cycle ORM способ выбран по уже существующему в кодовой базе
паттерну: `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php`
после записи мимо/через ORM вызывает `cleanOrmState()`
(`entityManager()->clean()` + `ORMInterface::getHeap()->clean()`), чтобы
последующий `findById()` перегидрировал сущность из БД, а не вернул stale-копию
из identity map.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php` | В `persistQueuedEvent()` после `markQueuedIfPublishing()` добавлен вызов `cleanOrmState()` перед `findById()`. Добавлен приватный метод `cleanOrmState()` (`entityManager()->clean()` + `getHeap()->clean()`) и импорт `Cycle\ORM\ORMInterface`. | Очистка EntityManager и ORM heap заставляет `findById()` перегидрировать строку из БД и вернуть актуальный статус `Queued` вместо stale `Publishing` из identity map. |

Тест не ослаблялся: `assertSame(OutboxEventStatus::Queued, ...)` остался без
изменений, смысл проверки сохранён (событие реально в статусе `queued` после
CAS). Боевой код (`OutboxEventRepository`, `StoredOutboxEvent`,
`OutboxQueueStatusInterceptor`) не менялся.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `phpunit --filter testInterceptorDoesNotRunJobWhenHeadersAndPayloadHaveDifferentOutboxIds OutboxQueueStatusInterceptorTest.php` | ✓ | Ранее падавший тест: OK (1 test, 4 assertions). |
| `phpunit OutboxQueueStatusInterceptorTest.php OutboxQueueStatusInterceptorFailureTest.php` | ✓ | Соседние тесты на `persistQueuedEvent()`: OK (21 tests, 58 assertions). |
| `composer phpstan` | ✓ | No errors. |
| `composer cs` (php-cs-fixer dry-run) | ✓ | Found 0 of 383 files that can be fixed. |

QA / ручные тесты / coverage не запускались по условию задачи.

## Открытые вопросы

Нет.
