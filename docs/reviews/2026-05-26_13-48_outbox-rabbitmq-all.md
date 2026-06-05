---
title: Ревью всех текущих изменений outbox RabbitMQ
date: 2026-05-26 13:48
target: git diff HEAD + untracked docs/fixes
mode: normal
score: 60
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: все текущие изменения outbox RabbitMQ

## Оценка

**60/100.** Проверки `docker compose -f docker/docker-compose.dev.yml --env-file .env config`, `make test` и `make phpstan` проходят, но в главном сценарии outbox остаются риски гонок между relay и worker, а часть тестов зависит от порядка запуска.

## Проблемы сверки с планом

### 1. Перевод в `queued` не сделан условным обновлением в базе

Статус: частично

Контекст:
План требовал после успешной отправки в RabbitMQ переводить событие в `queued` только если в базе оно всё ещё находится в статусе `publishing`.

Проблема:
Код проверяет только статус объекта в памяти. Если RabbitMQ consumer успеет обработать сообщение и поставить `handled` до сохранения `queued`, relay может записать старое состояние поверх уже обработанного события.

Риск:
Outbox может потерять финальный статус `handled`. Тогда событие выглядит не обработанным, хотя Job уже выполнил внешнее действие.

Где:

- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:74` — план требует условный update по статусу
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:103` — то же требование в целевом алгоритме
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:133` — проверяется только локальное поле Entity
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:134` — после этого статус сохраняется как `queued`

### 2. `FOR UPDATE SKIP LOCKED` из плана не реализован

Статус: частично

Контекст:
План отдельно требовал брать пачку outbox-событий через `FOR UPDATE SKIP LOCKED`, чтобы параллельные relay-процессы не ждали друг друга на уже занятых строках.

Проблема:
Repository использует только `forUpdate()`. В отдельном fix-документе прямо зафиксировано, что `SKIP LOCKED` был пропущен из-за ограничения Query Builder.

Риск:
Если запустить несколько relay-процессов, один процесс может ждать блокировку строк, которые уже забрал другой. При одном relay это остаётся отклонением от плана; при нескольких relay это становится эксплуатационным риском.

Где:

- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:54` — требование к короткой транзакции с `SKIP LOCKED`
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:72` — принятое решение про `FOR UPDATE SKIP LOCKED`
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:95` — алгоритм выборки требует `SKIP LOCKED`
- `app/src/Modules/Outbox/Repository/OutboxEventRepository.php:41` — выборка строится через ORM Select
- `app/src/Modules/Outbox/Repository/OutboxEventRepository.php:53` — есть только `forUpdate()`
- `docs/fixes/2026-05-25_20-14_forbid-raw-sql-query-builder.md:27` — зафиксировано, что `SKIP LOCKED` не добавлен

## Замечания

### 1. Relay может перезаписать `handled` обратно в `queued`

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
После отправки сообщения в RabbitMQ consumer может получить и обработать задачу почти сразу. Это нормальный сценарий для очереди: relay и worker работают независимо.

Риск:
Если worker успеет поставить `handled`, relay всё равно смотрит на старый объект в памяти и сохраняет `queued`. В итоге внешнее действие уже выполнено, но outbox больше не хранит правильный финальный статус.

Где:

- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:116` — сообщение отправляется в очередь
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:133` — проверка делается по локальному объекту
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:134` — локальный объект переводится в `queued`
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:73` — consumer может поставить `handled`
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:34` — проверен только sync-сценарий в одном процессе

Детали:
В тестах есть проверка sync-сценария и обычного push в fake queue, но нет проверки межпроцессной гонки relay и worker. В реальном RabbitMQ worker работает отдельно, поэтому локальный объект relay не узнает, что строка уже стала `handled`.

Как исправить:
Нужно обновлять статус `queued` атомарно в базе, с условием по текущему статусу строки.

Конкретные шаги:

- Добавить в repository метод вроде `markQueuedIfPublishing(OutboxEventId $id, DateTimeImmutable $now): bool`.
- Внутри метода использовать Query Builder `update()` с условиями `id = ...` и `status = publishing`.
- После условного update не сохранять старую Entity через обычный `persist()`.
- Добавить тест, где между `push()` и попыткой поставить `queued` событие уже стало `handled` через отдельный объект или отдельный EntityManager.

### 2. Для `SKIP LOCKED` не выбран единый контракт

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
Outbox relay может запускаться несколькими процессами. В таком режиме каждый процесс должен быстро брать свободные события и не ждать строки, которые уже забрал соседний процесс.

Риск:
Сейчас план требует `SKIP LOCKED`, а правила запрещают ручной SQL и отправляют код к ORM/Query Builder. Из-за этого реализация выбрала `FOR UPDATE` без `SKIP LOCKED`. При одном relay это отклонение от плана, при нескольких relay это снижает надёжность и скорость доставки.

Где:

- `app/src/Modules/Outbox/Repository/OutboxEventRepository.php:41` — выборка pending/publishing событий
- `app/src/Modules/Outbox/Repository/OutboxEventRepository.php:53` — используется только `FOR UPDATE`
- `vendor/cycle/database/src/Driver/Compiler.php:200` — Cycle компилирует только `FOR UPDATE`
- `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php:53` — тест проверяет фильтрацию, но не конкуренцию relay-процессов

Детали:
Поздний fix-документ уже описывает компромисс: сырой SQL запретили, а Query Builder не дал отдельный API для `SKIP LOCKED`. Но из-за этого плановое требование по параллельной обработке осталось невыполненным.

Как исправить:
Нужно выбрать явную политику и привести план, правила и код к одному контракту: либо поддержать `SKIP LOCKED` точечным безопасным способом, либо зафиксировать, что relay запускается строго в одном экземпляре.

Конкретные шаги:

- Если несколько relay-процессов нужны, добавить способ выполнить `FOR UPDATE SKIP LOCKED` и описать исключение из правила про ручной SQL.
- Если несколько relay-процессов не нужны, обновить план и документацию эксплуатации: relay должен быть один.
- Добавить тест или отдельную проверку SQL/конкуренции, чтобы это больше не терялось.

### 3. Presentation-слой зависит от Infrastructure-слоя

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
В проекте Presentation-слой должен идти через Application своего модуля. Для консольных команд правило ещё строже: команда только читает ввод, создаёт Command DTO и делегирует в Handler.

Риск:
Сейчас консольная команда напрямую вызывает infrastructure worker, а Job берёт константы заголовков из Infrastructure. Если сценарий relay и реальные Job будут развиваться, часть контракта останется между Presentation и Infrastructure, минуя Application-слой.

Где:

- `docs/rules.md:46` — консольная команда делегирует в CQRS Command+Handler
- `docs/arch.md:241` — Presentation зависит от Application своего модуля
- `docs/arch.md:341` — поток консольной команды идёт через Application Command
- `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:8` — Presentation импортирует Infrastructure worker
- `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:23` — worker внедряется прямо в команду
- `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:28` — команда напрямую запускает loop
- `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:34` — команда напрямую запускает разовую обработку
- `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:8` — Job импортирует Infrastructure-класс с заголовками
- `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:25` — Job читает outbox headers через Infrastructure-класс

Детали:
План противоречив: в одном месте он говорит про запуск relay через Command Handler, а ниже прямо упоминает `OutboxRelayWorker`. Но правила проекта и архитектура требуют не держать прямую зависимость Presentation -> Infrastructure.

Как исправить:
Нужно сначала выбрать единый контракт для console/job flow, затем под него поправить зависимости.

Конкретные шаги:

- Решить, где должен жить контракт outbox headers, чтобы Job не импортировал Infrastructure напрямую.
- Добавить Command DTO для запуска relay.
- Добавить Handler, который вызывает relay/worker и возвращает количество обработанных событий.
- В `OutboxRelayCommand` создать DTO и вызвать `CommandBus::dispatch(...)`.
- Для loop-режима оставить цикл в отдельном Application-сервисе или Handler-е, но не держать прямую зависимость Presentation -> Infrastructure.
- Обновить тест консольной команды так, чтобы он проверял вызов Application-сценария.

### 4. Outbox feature-тесты зависят от порядка запуска

Тип: `test`

Рекомендация: `править обязательно`

Контекст:
Feature-тесты, которые пишут в одну таблицу, должны сами готовить своё состояние. Иначе результат зависит от того, какие тесты запускались до них.

Риск:
Полный запуск сейчас проходит в текущем порядке, но отдельный или переупорядоченный запуск outbox-тестов может получить чужие `pending`/`publishing` записи и начать обрабатывать не только своё событие. Такой тест перестаёт надёжно защищать поведение команды relay.

Где:

- `tests/TestCase.php:40` — базовый тест не очищает БД между тестами
- `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:15` — тест команды не очищает `outbox_events`
- `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:27` — команда запускается на общей таблице
- `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:29` — тест ожидает ровно одно обработанное событие
- `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php:53` — другой feature-тест создаёт несколько pending/publishing событий
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:27` — очистка есть только в одном классе outbox-тестов

Детали:
`OutboxRelayTest` чистит таблицу в `setUp()`, но `OutboxRelayCommandTest`, `OutboxEventRepositoryTest` и `OutboxQueueStatusInterceptorTest` этого не делают. Поэтому порядок тестов влияет на содержимое `outbox_events`.

Как исправить:
Нужно сделать outbox feature-тесты изолированными по данным.

Конкретные шаги:

- Вынести очистку `outbox_events` в общий helper или базовый trait для outbox feature-тестов.
- Использовать очистку во всех feature-тестах, которые создают outbox-события.
- Добавить проверку команды так, чтобы она не зависела от чужих записей в таблице.

## Рекомендации

- **Править обязательно:** 1, 2, 3, 4
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** проблема изоляции outbox feature-тестов; зависимость `OutboxDebugLogJob` от Infrastructure-класса с заголовками.
- **~ Изменено:** пункт про консольную команду переформулирован как нарушение границы Presentation -> Infrastructure и конфликт правил с планом; пункт про `SKIP LOCKED` переформулирован как конфликт плана, правил и режима запуска relay; тестовый пробел по гонке relay/worker уточнён как отсутствие межпроцессного сценария.
- **− Убрано:** жёсткая формулировка, что консольная команда однозначно нарушает план, потому что сам план содержит противоречие.
- **Отклонено:** предложение написать, что полный `make test` не проходит, отклонено: в этом запуске `make test` прошёл успешно; предложение добавить проблему про любую битую строку outbox, валящую batch, отклонено как недостаточно доказанное для штатного контракта хранения FQCN.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-05-26_14-34_outbox-rabbitmq-all.md`
