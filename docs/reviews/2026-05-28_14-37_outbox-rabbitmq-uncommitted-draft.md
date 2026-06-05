---
title: Черновое ревью незакоммиченных изменений outbox RabbitMQ после фиксов
date: 2026-05-28 14:37
target: git diff HEAD + staged changes + untracked files
mode: normal
score: 72
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ после фиксов

## Оценка

**72/100.** Основные аварийные сценарии из прошлого ревью закрыты: старый тип сообщения больше не ломает чтение до записи ошибки, serializer умеет восстанавливать payload без класса, а тесты разделены лучше. После мета-ревью остались четыре обязательных риска: queue Job может выполниться без обновления outbox-статуса, индекс git расходится с рабочим деревом, Job содержит бизнес-действия вместо тонкого адаптера, а новый глобальный ORM mapper покрыт слишком узко.

## Проблемы сверки с планом

План не проверен: нужен путь к файлу плана. Ревью было запрошено без плана, поэтому plan-check не пытался угадывать исходные требования.

## Замечания

### 1. Outbox Job может выполниться без обновления статуса, если заголовки очереди потеряются

Outbox-событие кладётся в очередь так, что идентификатор есть и в payload, и в headers. Но interceptor, который отвечает за перевод события в `handled` или `failed`, ищет `outboxId` только в headers.

Если RabbitMQ, RoadRunner или будущий publisher передаст payload корректно, но не передаст headers, Job всё равно выполнится как обычная задача. Внешнее действие может уйти, а outbox-событие останется в `queued`; потом его будет сложно отличить от зависшей задачи. Это нарушает архитектурную гарантию outbox: общий queue interceptor должен менять статусы outbox и отвечать за ошибки queue Job.

Технические детали:

- **Тип:** `bug`, `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:34` — interceptor берёт идентификатор только через `outboxEventIdFromParameters()`.
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:36` — при отсутствии `outboxId` interceptor вызывает Job как обычную задачу.
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:89` — `outboxEventIdFromParameters()` читает только `headers`.
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php:36` — publisher отправляет outbox envelope в payload.
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueEnvelope.php:13` — envelope уже содержит `outboxEventId`.
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php:33` — тест с пустыми headers сейчас закрепляет пропуск Job как обычной задачи.
- **Что подтверждает проблему:** данные для определения outbox-события есть в payload, но interceptor их не использует. Ветка без headers логирует пропуск и запускает `$core->callAction(...)`, поэтому успешный Job не будет отмечен как `handled`, а упавший Job не будет записан как `failed`.
- **Как исправить:** в interceptor сначала читать `outboxId` из headers, а если его нет — брать из `$parameters['payload']`, когда payload является `OutboxQueueEnvelope`. Если есть и headers, и payload, лучше проверить, что идентификаторы совпадают.
- **Тесты:** добавить feature-тест, где `OutboxQueueStatusInterceptor` получает `OutboxQueueEnvelope` в payload и пустые headers; после успешного Job событие должно стать `handled`, после ошибки — `failed`.

### 2. Индекс git расходится с рабочим деревом и может сохранить старые границы outbox

Текущий рабочий каталог и индекс git расходятся. В staged добавлен старый файл `Domain\OutboxMessage`, который уже удалён из рабочего дерева, а новый файл `Application\Outbox\OutboxMessage` ещё не добавлен в индекс. Это не единственный риск: часть файлов помечена как `AM`, поэтому staged-версия может отличаться от текущей исправленной версии.

Если после такого состояния сделать обычный `git commit` без `git add -A`, в коммит может попасть устаревшая граница модуля и не попасть новая. Также в историю может попасть staged-версия `OutboxEventRepository`, где транзакция и технический `DatabaseInterface` всё ещё находятся внутри репозитория, хотя текущие правила это запрещают.

Технические детали:

- **Тип:** `process`, `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Domain/OutboxMessage.php` — файл staged как добавленный, но в рабочем дереве удалён.
- **Где:** `app/src/Modules/Outbox/Application/Outbox/OutboxMessage.php:5` — актуальный интерфейс находится в Application-слое, но сейчас untracked.
- **Где:** `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` — staged-версия отличается от рабочей и содержит старую реализацию с транзакцией внутри Repository.
- **Где:** `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php` — staged-версия ещё обращается к инфраструктурному worker напрямую, а рабочая версия уже делегирует через Application-сценарий.
- **Что подтверждает проблему:** `git status --short` показывает `AD app/src/Modules/Outbox/Domain/OutboxMessage.php`, `?? app/src/Modules/Outbox/Application/Outbox/OutboxMessage.php` и много `AM`-файлов в outbox-модуле. Это значит, что индекс не соответствует итоговому рабочему дереву.
- **Как исправить:** перед фиксацией привести индекс к рабочему дереву: добавить удаление старого файла, новые Application-файлы и все unstaged-исправления в `AM`-файлах. После этого `git status --short` не должен показывать `AD` для старого пути и не должен оставлять важные outbox-файлы в состоянии `AM`.
- **Тесты:** отдельный тест не нужен; достаточно проверить `git status --short`, `git diff --cached --name-status` и убедиться, что staged-состояние соответствует итоговому рабочему дереву.

### 3. Queue Job содержит бизнес-действия вместо тонкого адаптера

`OutboxDebugLogJob` сам читает outbox-событие из репозитория, сверяет тип и десериализует сообщение. По архитектуре проекта Job должен быть техническим адаптером очереди: принять payload, создать Command DTO, отправить его в Application Handler и не держать бизнес-сценарий внутри Presentation-слоя.

Сейчас для debug Job это выглядит небольшим кодом, но этот класс задаёт шаблон для будущих реальных Job: email, push, Centrifugo и webhooks. Если повторить такой подход, Presentation-слой начнёт содержать выборку из БД, восстановление бизнес-сообщения и проверки сценария.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:20` — метод `invoke()` принимает инфраструктурные зависимости и выполняет сценарий сам.
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:24` — Job напрямую получает `OutboxEventRepository`.
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:25` — Job напрямую получает serializer.
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:28` — Job читает событие из репозитория.
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:35` — Job сам десериализует outbox-сообщение.
- **Что подтверждает проблему:** `docs/arch.md` описывает Job handler как технический адаптер очереди, а бизнес-сценарий должен находиться в Application Handler. Этот Job нарушает такой поток.
- **Как исправить:** вынести сценарий обработки debug-сообщения в Application Command/Handler, а Job оставить тонким адаптером: принять envelope, создать Command и вызвать `CommandBusInterface`.
- **Тесты:** обновить feature-тест Job так, чтобы он проверял делегирование в Application-сценарий, и оставить отдельные тесты самого Handler-а.

### 4. Новый глобальный LazyGhostMapper покрыт слишком узко для такого риска

`app/config/cycle.php` меняет mapper по умолчанию для всей ORM-схемы на `LazyGhostMapper`. Это затрагивает восстановление всех Entity, relation reference, lazy-object состояния и сохранение после гидрации. При этом самая сложная логика в `LazyGhostEntityFactory` покрыта только одним happy-path тестом на Media relations.

Если mapper ошибётся на другом типе связи, наследовании, уже инициализированном lazy object или readonly-свойстве, это может ломать не только outbox, а любую сущность приложения. Такой общий инфраструктурный слой требует более широких проверок.

Технические детали:

- **Тип:** `quality`, `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `app/config/cycle.php:25` — mapper по умолчанию меняется на `LazyGhostMapper`.
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:38` — основная логика `upgrade()` работает с relation map, lazy-state и raw-записью свойств.
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:135` — `extractRelations()` возвращает pending relation references или реальные значения.
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:166` — initializer вручную восстанавливает pending references.
- **Где:** `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:91` — есть только один happy-path тест на Media relations и сохранение после восстановления.
- **Что подтверждает проблему:** изменение глобальное, а проверка покрывает один набор связей и один удачный путь. Нет отдельных кейсов на разные relation-типы, отсутствие relation property, уже инициализированные lazy objects, readonly-свойства и сохранение Entity без предварительной инициализации всех relations.
- **Как исправить:** добавить focused feature-тесты для `LazyGhostMapper` на разные relation-сценарии и состояния lazy-объекта. Если часть сценариев не нужна проекту, явно зафиксировать это тестом или ограничением.
- **Тесты:** расширить покрытие mapper-а до нескольких независимых кейсов: `BelongsTo`, коллекционная связь, сохранение lazy Entity без чтения relation, повторное сохранение после инициализации relation.

## Рекомендации

- **Править обязательно:** 1, 2, 3, 4
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** замечание про бизнес-действия внутри `OutboxDebugLogJob`; замечание про недостаточное покрытие глобального `LazyGhostMapper`.
- **~ Изменено:** проверка плана теперь записана как непроверенная без пути к плану; пункт про headers усилен как нарушение outbox-гарантии; пункт про git-состояние расширен с пары `Domain/OutboxMessage` / `Application/Outbox/OutboxMessage` до риска по staged/working-tree расхождениям в нескольких outbox-файлах.
- **− Убрано:** нет.
- **Отклонено:** отдельный пункт про неизвестный запуск `make test`, `make phpstan` и `make qa` не добавлен, потому что в мета-проверке эти команды не запускались, а в diff и тексте ревью нет логов, по которым можно честно проверить их выполнение; предложение убрать git-состояние из quality-блока применено как смена типа на `process`, а не удаление пункта.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-05_11-53_outbox-rabbitmq-uncommitted-draft.md`
