---
title: Ревью незакоммиченных изменений outbox RabbitMQ без плана
date: 2026-05-28 12:35
target: git diff HEAD + untracked files
mode: normal
score: 45
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ без плана

## Оценка

**45/100.** В основном контуре outbox есть важная проблема с обработкой ошибок до запуска status-interceptor, а официальный `make qa` сейчас не завершается успешно. Дополнительно публичный контракт outbox протаскивает наружу внутренние доменные типы, а глобальная замена mapper-а Cycle не закрыта прямыми тестами.

## Проблемы сверки с планом

Проверка выполнения плана пропущена по явному указанию пользователя: ревью запрошено «без плана».

## Замечания

### 1. Официальная QA-команда сейчас падает по таймауту

Новый обязательный путь проверки должен быть рабочим сам по себе. Сейчас `make qa` доходит до PHPUnit и падает из-за лимита Composer в 300 секунд, поэтому разработчик не может закрыть глобальную задачу по новым правилам проекта.

Главная практическая проблема в том, что весь `composer qa` запускается с включённым режимом покрытия. Из-за этого даже обычный шаг `composer test` идёт медленно и не успевает завершиться до стандартного таймаута Composer.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `docker/test/run-qa.sh:8` — вся QA-команда запускается как `XDEBUG_MODE=coverage composer qa`
- **Где:** `composer.json:102` — обычные тесты запускаются через `phpunit`
- **Где:** `composer.json:103` — покрытие запускается отдельным шагом `phpunit --coverage-text`
- **Где:** `composer.json:104` — `qa` последовательно запускает `cs`, `phpstan`, `test`, `test-coverage`
- **Где:** `AGENTS.md:64` — `make qa` добавлен как обязательная проверка
- **Что подтверждает проблему:** я запустил `make qa`; PHP CS Fixer и PHPStan прошли, затем PHPUnit не завершился и Composer остановил процесс с ошибкой: `The process "phpunit" exceeded the timeout of 300 seconds.`
- **Как исправить:** не включать coverage для всего `composer qa`. Запускать обычные тесты без покрытия, а `XDEBUG_MODE=coverage` включать только для шага покрытия; либо разделить Docker-скрипт на явные команды `composer cs`, `composer phpstan`, `composer test`, `XDEBUG_MODE=coverage composer test-coverage` и при необходимости задать осознанный `COMPOSER_PROCESS_TIMEOUT`.
- **Тесты:** после изменения повторить именно `make qa`, потому что проблема проявляется только на полном официальном пути проверки.

### 2. Ошибка восстановления payload может оставить событие в вечном `queued`

Outbox помечает событие `handled` или `failed` через общий interceptor очереди. Но восстановление payload из RabbitMQ происходит раньше, чем этот interceptor получает управление. Если восстановление сообщения упадёт, событие уже будет считаться отправленным в очередь, а код outbox не сможет записать ошибку в таблицу.

Риск проявится при битом payload, несовместимом изменении класса сообщения после деплоя, удалённом классе, ошибке Valinor-маппинга или неверной связке Job и типа сообщения. Ещё один вариант — зарегистрировать outbox Job как обычный `HandlerInterface` без `invoke($payload)`: такой класс считается допустимым, но RoadRunner bridge не сможет вывести тип payload для serializer-а.

В результате RabbitMQ-задача может быть отклонена или потеряна по правилам брокера, а строка в `outbox_events` останется в статусе `queued` без `failed_at` и без понятной причины.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php:38` — relay кладёт задачу в очередь
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:102` — после успешного push событие переводится в `queued`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:30` — serializer восстанавливает доменное сообщение до запуска Job
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:32` — при неверном или отсутствующем типе бросается исключение
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:39` — Valinor-десериализация тоже может бросить исключение
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxJobRegistry.php:27` — registry принимает любой `HandlerInterface`, не только Job с выводимым типом payload
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:66` — outbox-status записывается только вокруг вызова `$core->callAction(...)`
- **Что подтверждает проблему:** RoadRunner bridge вызывает `PayloadDeserializer` до передачи задачи в consume handler: `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Dispatcher.php:65`. Header `payload_class` добавляется только если payload является объектом: `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Queue.php:98`, а outbox отправляет array-envelope.
- **Как исправить:** закрыть рискованную десериализацию внутри outbox-инфраструктуры, которая уже умеет менять статус события. Например, класть в очередь минимальный envelope с `outboxId`, а доменное сообщение восстанавливать в отдельном outbox-dispatch handler-е после того, как событие найдено по `outboxId`; при ошибке восстановления переводить событие в `failed` с `last_error`.
- **Тесты:** добавить feature-тест, который имитирует ошибку десериализации payload для outbox-задачи и проверяет, что событие не остаётся в `queued`, а получает конечный или повторяемый статус с сохранённой ошибкой.

### 3. Сущность outbox открыта для произвольной записи состояния

У outbox-события есть важные переходы: `pending`, `publishing`, `queued`, `handled`, `failed`. Сейчас все свойства сущности публично изменяемые, поэтому любой код может напрямую поменять статус, попытки или даты и обойти методы, которые поддерживают связанное состояние.

Такой доступ делает инварианты хрупкими. Например, можно выставить `failed` без `failedAt`, увеличить `attempts` без `lastError` или поменять `handledAt` без `queuedAt`. Это особенно опасно для outbox, потому что статусы используются как механизм надёжной доставки.

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:32` — `id` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:41` — `status` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:44` — `attempts` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:47` — даты доставки публично изменяемые
- **Что подтверждает проблему:** правила проекта требуют менять Entity через доменные методы и использовать property hooks вместо открытых геттеров/сеттеров; см. `docs/rules.md:34`. Правило по VO прямо показывает стиль `public private(set)`; см. `docs/rules.md:37`. Существующие media-сущности следуют этому стилю, например `app/src/Modules/Media/Domain/Entity/Media.php:43`.
- **Как исправить:** закрыть запись свойств через `public private(set)` или явные property hooks, а изменение статусов оставить только через доменные методы `markPublishing()`, `markQueued()`, `markHandled()`, `recordJobRetry()`, `markFailed()` и `recordPublishFailure()`. Тестам, которым нужно подготовить число попыток, лучше использовать доменный helper/fixture или сценарий через существующие методы, а не прямую запись.
- **Тесты:** обновить тесты, которые сейчас напрямую меняют `attempts`, и добавить проверку нужных переходов статусов через публичные доменные методы.

### 4. Публичный контракт outbox заставляет другие модули зависеть от его Domain

Outbox должен быть модулем, к которому другие части приложения обращаются через Application-слой. Сейчас публичный контракт для добавления события принимает и возвращает типы из Domain-слоя самого outbox. Значит любой модуль, который захочет отправить outbox-сообщение, будет вынужден импортировать внутренний Domain outbox.

Это размывает границу модуля. Со временем обычные модули начнут зависеть не от сценария outbox, а от его внутренней модели хранения и идентификаторов, из-за чего менять реализацию outbox будет сложнее.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:7` — Application-контракт принимает `OutboxMessage` из Domain
- **Где:** `app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:8` — Application-контракт возвращает `OutboxEventId` из Domain
- **Где:** `app/src/Modules/Outbox/Domain/OutboxMessage.php:7` — публичный marker-интерфейс лежит в Domain
- **Что подтверждает проблему:** архитектура проекта говорит, что другие модули обращаются к модулю через `Application`, а `Repository` и `Domain` не являются публичным API модуля.
- **Как исправить:** вынести публичный тип сообщения в `Application/Outbox` или `Application/Contract`, чтобы отправители зависели от Application-API outbox. Возвращаемое значение `add()` лучше заменить на Application-DTO или убрать, если внешний модуль не должен знать внутренний `outbox_events.id`.
- **Тесты:** обновить тесты отправки outbox-события через контракт и проверить, что внешний код не импортирует `App\Modules\Outbox\Domain`.

### 5. Реальный RabbitMQ-путь почти не проверяется

Сейчас тесты хорошо проверяют sync-режим и fake queue, но реальный путь через RabbitMQ отличается: payload сериализуется в строку, затем RoadRunner bridge восстанавливает тип payload, а только потом запускает consume-chain. Именно в этом месте находится один из самых важных рисков outbox.

Текущий feature-тест принимает сразу два разных формата payload, поэтому он не фиксирует контракт RabbitMQ-transport как единственно правильный. Unit-тест serializer-а проверяет ручной happy path, но не проверяет связку publisher, RoadRunner headers, registry и status-interceptor.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:131` — проверка fake queue допускает разные payload-форматы
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:137` — sync-payload считается допустимым
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:139` — transport-envelope тоже считается допустимым
- **Где:** `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializerTest.php:16` — serializer проверяется отдельно от реального consume path
- **Что подтверждает проблему:** `QUEUE_CONNECTION` для test-runner выставлен в `sync`, а RabbitMQ pipeline не поднимается в test-профиле. Поэтому реальная десериализация RoadRunner/RabbitMQ не проходит через feature-тесты.
- **Как исправить:** добавить отдельный интеграционный тест или узкий компонентный тест, который воспроизводит именно RabbitMQ/RoadRunner путь: publisher создаёт transport payload, bridge/serializer восстанавливает payload, status-interceptor видит outbox headers и корректно меняет статус.
- **Тесты:** новый тест должен падать, если убрать transport keys, сломать `OutboxQueueSerializer::unserialize()` или зарегистрировать Job без выводимого payload-типа.

### 6. Глобальная замена Cycle mapper-а не покрыта прямыми тестами

В diff включён новый mapper для всех Entity проекта. Это низкоуровневое изменение: оно влияет не только на outbox, а на всю гидрацию ORM, lazy relations, чтение `private(set)` свойств и сохранение связей.

Сейчас есть косвенные repository-тесты, но нет прямого теста на сам новый механизм. Если lazy relation, pending reference или извлечение relation-данных сломается, часть сценариев может начать падать уже в unrelated-модулях, а причина будет неочевидной.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `app/config/cycle.php:25` — `LazyGhostMapper` включён как mapper по умолчанию для схемы Cycle
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:43` — mapper создаёт lazy ghost Entity при инициализации
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:33` — используется Reflection `newLazyGhost`
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:62` — relation reference откладывается в `WeakMap`
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:170` — initializer позже восстанавливает отложенные связи
- **Что подтверждает проблему:** в diff нет отдельного теста для `LazyGhostMapper` или `LazyGhostEntityFactory`; существующие тесты проверяют работу репозиториев на happy path, но не фиксируют lazy relation до и после первого доступа.
- **Как исправить:** добавить прямые feature-тесты для ORM-гидрации через сущности Media: восстановить Entity с `private(set)` свойствами, проверить lazy `BelongsTo` и `HasMany` до/после доступа, затем сохранить изменённую Entity и убедиться, что связи не теряются.
- **Тесты:** отдельный тест должен явно обращаться к relation-свойствам и проверять, что `LazyGhostMapper` не ломает сохранение обычных полей и связей.

## Рекомендации

- **Править обязательно:** 1, 2, 3, 4, 5, 6
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** проблема с утечкой `Domain` outbox через публичный Application-контракт; проблема с недостаточной проверкой реального RabbitMQ/RoadRunner пути; проблема с отсутствием прямых тестов на глобальный `LazyGhostMapper`.
- **~ Изменено:** уточнён пункт про десериализацию payload до status-interceptor; уточнён пункт про открытую запись в `StoredOutboxEvent`; оценка снижена с 58 до 45.
- **− Убрано:** нет.
- **Отклонено:** отдельный пункт про ошибку разбора headers в `OutboxQueueStatusInterceptor` не добавлен, потому что при невалидном `outboxId` код ещё не знает, какое сохранённое событие нужно перевести в ошибку; этот риск частично покрывается общим пунктом про ошибки до status-interceptor.
