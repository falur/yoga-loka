---
review: docs/reviews/2026-06-07_14-17_outbox-module-draft.md
date: 2026-06-07 14:40
status: done
---

# Фиксы по ревью: Модуль Outbox

Режим: `apply-optional` (применяются и «править обязательно», и «на усмотрение
автора»; «не править» пропускается).

Контекст: предыдущий запуск этого фикса был прерван по лимиту сессии и не сохранил
отчёт. Рабочее дерево осталось в частично применённом состоянии. Перед правками
сверено текущее состояние кода с каждым замечанием. Итог: все пять пунктов уже
были применены прошлым (прерванным) запуском. Дублирующих правок не вносилось.
Дополнительно проверена согласованность частично применённых правок (debug-слой
подключён и используется, Job делегирует в Handler).

## Применённые правки

| # | Замечание | Рекомендация | Файлы | Тесты | Статус |
|---|-----------|--------------|-------|-------|--------|
| 1 | Debug-Job должен делегировать в Application Handler, не делать действие сам; убрать противоречие с README | править обязательно | `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`, `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageCommand.php`, `...ProcessOutboxDebugLogMessageHandler.php`, `app/src/Modules/Outbox/README.md` | `tests/Unit/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php` (1 ✓) | ✓ уже применено (прошлый запуск), сверено |
| 2 | Мёртвый метод `StoredOutboxEvent::markQueued()` | править обязательно | `app/src/Modules/Outbox/Domain/Entity/StoredOutboxEvent.php` | удалены прямые проверки; переход покрыт `markQueuedIfPublishing` | ✓ уже применено (прошлый запуск), сверено |
| 3 | Мёртвый VO-метод `OutboxAvailableAt::isAvailableAt()` | править обязательно | `app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php` | ассерты убраны из `OutboxValueObjectTest` | ✓ уже применено (прошлый запуск), сверено |
| 4 | Дублирование ключей транспортного payload очереди | на усмотрение автора | `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php` | существующие тесты сериализатора зелёные | ✓ уже применено (прошлый запуск), сверено |
| 5 | «Висящие» `AD`-файлы debug Application-слоя в индексе | на усмотрение автора | `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/*` | — | ✓ уже применено (прошлый запуск), сверено |

## Что именно сверено по каждому пункту

- **1.** `OutboxDebugLogJob::invoke()` грузит сообщение через
  `OutboxMessageLoaderContract`, собирает `ProcessOutboxDebugLogMessageCommand` и
  диспатчит его через `CommandBusInterface` в
  `ProcessOutboxDebugLogMessageHandler` — тонкий адаптер, как эталонный
  `SendWelcomeEmailJob` в README. README `:475-487` теперь корректно ссылается на
  Job, у которого реально есть Handler. Противоречие документации и кода устранено.
  Пара сообщение→Job зарегистрирована в `OutboxBootloader` и в
  `app/config/queue.php` (handler + serializer). Тестовый фикстур
  `QueueStatusDebugLogJobCore` и тест `OutboxDebugLogJobTest` проверяют
  делегирование в Handler.
- **2.** Метода `markQueued()` нет ни в `app/src`, ни в `tests`
  (grep пустой). Боевой переход publishing→queued — это `markQueuedIfPublishing`
  (CAS) в `OutboxEventRepository`, вызывается из `OutboxRelay`. Тест-хелпер
  `OutboxQueueStatusInterceptorTestHelpers::persistQueuedEvent()` тоже использует
  CAS-переход.
- **3.** Метода `isAvailableAt()` нет ни в `app/src`, ни в `tests`. Критерий
  доступности остаётся единственным — `where('available_at','<=',$now)` в
  репозитории.
- **4.** В `OutboxQueueSerializer` больше нет приватных `OUTBOX_ID`/`OUTBOX_TYPE`;
  он переиспользует публичные `OutboxQueueHeaders::OUTBOX_ID` /
  `OutboxQueueHeaders::OUTBOX_TYPE` как единый источник ключей. Интерцептор и
  сериализатор теперь читают один и тот же контракт.
- **5.** Файлы debug Command/Handler присутствуют на диске и в индексе в статусе
  `A` (не `AD`). Рассинхрон индекса и рабочего дерева устранён согласно решению
  по пункту 1 (debug Application-слой сохранён).

## Финальная проверка

- **Статанализ (PHPStan):** `composer phpstan` (через Docker) — ✓ `[OK] No errors`.
- **Линтер (php-cs-fixer):** `composer cs` (dry-run, через Docker) — ✓ `Found 0 of
  382 files that can be fixed`.
- **Тесты (точечно, без coverage):** `vendor/bin/phpunit --testsuite Unit --filter
  Outbox --no-coverage` (через Docker) — ✓ `OK` 58 тестов, 163 ассерта. 2
  PHPUnit-deprecations (не падения).
- **Заметки:** Полный сьют и coverage не запускались по ограничениям задачи.
  QA/ручные тесты не запускались.
</content>
</invoke>
