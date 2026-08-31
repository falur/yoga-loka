---
title: Третий черновой обзор Outbox-среза после исправлений
date: 2026-06-07 13:14
target: git diff HEAD, Outbox-related unstaged and staged changes
plan: none
mode: normal
score: 84
status: meta-reviewed
meta_reviewers:
  - architecture-check (gpt-5.5)
  - rules-check (gpt-5.5)
  - quality-check (gpt-5.5)
---

# Ревью: Третий черновой обзор Outbox-среза после исправлений

## Оценка

**84/100.** Явных новых поведенческих проблем в relay-потоке не найдено, но остались две обязательные проблемы: `OutboxDebugLogJob` выполняет действие прямо в Job, хотя архитектура описывает Job как тонкий адаптер к Application Handler, и индекс сейчас не совпадает с рабочим деревом по широкому набору целевых файлов.

## Проблемы сверки с планом

Проверка плана пропущена: пользователь явно запросил обзор без плана для Outbox-среза незакоммиченных изменений.

## Замечания

### 1. `OutboxDebugLogJob` выполняет действие прямо в Job, без Application Handler

В `docs/arch.md` поток задачи очереди описан так: `Presentation/Job handler` принимает payload, создаёт Application Command и отправляет его в Application Handler. Там же сказано, что Job handler — это технический адаптер очереди, а бизнес-сценарий находится в Application Handler.

Сейчас `OutboxDebugLogJob` сам загружает сообщение через `OutboxMessageLoaderContract` и пишет лог через `LoggerInterface`. Это не тонкий адаптер: действие выполняется прямо на границе очереди. Удалённые из рабочего дерева `ProcessOutboxDebugLogMessageCommand` и `ProcessOutboxDebugLogMessageHandler` как раз были слоем Application для этого действия, поэтому удаление этих файлов нельзя считать доказанным исправлением.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`
- **Что подтверждает проблему:** `docs/arch.md`, раздел «Поток задачи очереди», требует путь через Application Command и Handler. Текущий Job делает действие сам.
- **Как исправить:** вернуть или заменить Application Command + Handler для обработки debug-сообщения, а Job оставить адаптером: загрузить сообщение, создать команду и отправить её в Handler через `CommandBusInterface`.
- **Тесты:** обновить тест `OutboxDebugLogJobTest` так, чтобы он проверял делегирование в Application-сценарий, и оставить покрытие самого Handler-а.

### 2. Индекс не совпадает с рабочим деревом по целевым Outbox-файлам

Проблема шире, чем два `AD` handler-файла. По целевому Outbox-срезу сейчас есть 97 staged-файлов и 26 unstaged-файлов. Если сделать коммит только из уже подготовленного индекса, в него попадёт не то же состояние, которое видно в рабочей директории.

Например, `app/config/cycle.php`, `app/config/queue.php`, `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php`, `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php` и часть тестов имеют unstaged-изменения. Два удалённых debug handler-файла дополнительно находятся в состоянии `AD`: они добавлены в индекс, но удалены из рабочего дерева.

Технические детали:

- **Тип:** `process`
- **Рекомендация:** `править обязательно`
- **Где:** `git status --short`, `git diff --name-only --cached HEAD -- app/src/Modules/Outbox app/config app/database/migrations tests`, `git diff --name-only -- app/src/Modules/Outbox app/config app/database/migrations tests`
- **Что подтверждает проблему:** staged- и unstaged-составы отличаются. `git diff --name-only --cached ...` показывает 97 файлов, а `git diff --name-only ...` показывает 26 файлов с незастейдженными правками.
- **Как исправить:** сначала решить итоговое состояние для debug Application Handler-ов с учётом замечания 1, затем привести индекс к рабочему дереву для всего целевого Outbox-среза. После этого `git status --short` не должен показывать `AD` для debug handler-ов и неожиданные `AM`/`M` расхождения по файлам, которые должны войти в коммит.
- **Тесты:** отдельный тест не нужен. Достаточно проверить `git status --short` перед коммитом.

### 3. Ключи transport payload продублированы в двух местах

`outboxId` и `outboxType` являются одним транспортным контрактом очереди. Сейчас они заданы как публичные константы в `OutboxQueueHeaders` и как отдельные приватные константы в `OutboxQueueSerializer`. Это создаёт риск расхождения при будущих правках: headers и JSON payload могут начать использовать разные ключи.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Outbox/Infrastructure/OutboxQueueHeaders.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php`
- **Что подтверждает проблему:** оба класса отдельно задают строки `outboxId` и `outboxType`.
- **Как исправить:** использовать единый источник имён ключей, например константы `OutboxQueueHeaders`, если это не ухудшит ответственность класса.
- **Тесты:** существующие тесты сериализатора и headers должны продолжать проходить.

## Рекомендации

- **Править обязательно:** 2
- **На усмотрение автора:** 1

## Проверки

- `make qa` не запускался по прямому запрету пользователя.
- `composer qa` не запускался по прямому запрету пользователя.
- Coverage / проверки покрытия тестами не запускались по прямому запрету пользователя.
- Дополнительные `make phpstan` и `make test` в этом обзоре не запускались.

## Изменения после мета-ревью

plan-check не запускался: план не указан в ревью.

### После architecture-check / rules-check / quality-check

- **+ Добавлено:** замечание про `OutboxDebugLogJob`, который выполняет действие без Application Handler; замечание про общий рассинхрон индекса и рабочего дерева; замечание на усмотрение про дублирование ключей `outboxId` и `outboxType`.
- **~ Изменено:** оценка снижена с 92 до 84; пункт про `AD` handler-файлы расширен до общей проблемы состояния индекса; тип этого пункта изменён с `quality` на `process`.
- **− Убрано:** утверждение, что удаление debug Application Handler-ов соответствует исправлению.
- **Отклонено:** предложение добавить отдельный пункт про протаскивание `OutboxEventId` и `OutboxEventType` через `OutboxQueueEnvelope` и `OutboxMessageLoaderContract`: по `docs/arch.md` это не подтверждено как нарушение, потому что это внутренние типы своего Outbox-модуля. Пункты про незапущенные `make qa`, `composer qa` и coverage не добавлялись, потому что пользователь прямо запретил эти проверки в текущей сессии.
