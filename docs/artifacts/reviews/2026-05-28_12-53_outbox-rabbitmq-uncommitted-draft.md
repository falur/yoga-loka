---
title: Ревью незакоммиченных изменений outbox RabbitMQ без плана
date: 2026-05-28 12:53
target: git diff HEAD + untracked files
mode: normal
score: 43
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ без плана

## Оценка

**43/100.** Основной контур outbox уже собран, но в нём остаются риски для надёжной доставки: событие может зависнуть после ошибки восстановления сообщения, а реальные RabbitMQ/RoadRunner сценарии почти не проверены. После мета-ревью добавились подтверждённые проблемы с границей Application-слоя и с записью состояния вне Handler-а.

## Проблемы сверки с планом

План не проверен: нужен путь к файлу плана. Ревью было запрошено без плана, поэтому plan-check не пытался угадывать исходные требования.

## Замечания

### 1. Ошибка восстановления payload может оставить событие в вечном `queued`

Outbox помечает событие как обработанное или упавшее через общий перехватчик очереди. Но восстановление payload из RabbitMQ происходит раньше, чем этот перехватчик получает управление. Если сообщение не восстановится, outbox уже будет считать событие отправленным в очередь, но не сможет записать ошибку в таблицу.

Риск проявится при битом payload, несовместимом изменении класса сообщения после деплоя, удалённом классе, ошибке Valinor-маппинга или неверной связке Job и типа сообщения. В результате задача может быть отклонена брокером, а строка в `outbox_events` останется в статусе `queued` без `failed_at` и без понятной причины.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:100` — relay отправляет задачу в очередь
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:102` — после успешного push событие переводится в `queued`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:30` — serializer восстанавливает доменное сообщение до запуска Job
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:32` — при неверном или отсутствующем типе сообщения бросается исключение
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:39` — Valinor-десериализация тоже может бросить исключение
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:66` — статус outbox записывается только вокруг вызова `$core->callAction(...)`
- **Где:** `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Dispatcher.php:65` — RoadRunner bridge десериализует payload до запуска consume-handler
- **Где:** `vendor/spiral/roadrunner-bridge/src/Queue/Internal/PayloadDeserializer.php:50` — serializer вызывается до передачи задачи в цепочку обработки
- **Что подтверждает проблему:** `OutboxQueuePublisher` кладёт в RoadRunner array-envelope, поэтому `payload_class` не добавляется автоматически; это видно в `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Queue.php:98`. Ошибка в `unserialize()` произойдёт до `OutboxQueueStatusInterceptor`.
- **Как исправить:** перенести рискованное восстановление сообщения внутрь outbox-обработчика, который уже знает `outboxId` и может менять статус события. Например, класть в очередь минимальный envelope с `outboxId`, а доменное сообщение восстанавливать после поиска события по этому id; при ошибке восстановления переводить событие в `failed` или повторяемое состояние с сохранением `last_error`.
- **Тесты:** добавить feature-тест, который имитирует ошибку десериализации payload для outbox-задачи и проверяет, что событие не остаётся в `queued`.

### 2. Сущность outbox открыта для произвольной записи состояния

У outbox-события есть важные переходы: `pending`, `publishing`, `queued`, `handled`, `failed`. Сейчас все свойства сущности публично изменяемые, поэтому любой код может напрямую поменять статус, попытки или даты и обойти методы, которые поддерживают связанное состояние.

Это делает состояние outbox хрупким. Например, можно выставить `failed` без даты падения, увеличить попытки без ошибки или поставить `handled_at` без нормального перехода через очередь. Для механизма доставки такие обходы особенно опасны.

Технические детали:

- **Тип:** `rules`, `quality`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:32` — `id` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:41` — `status` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:44` — `attempts` публично изменяемый
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:47` — даты доставки публично изменяемые
- **Что подтверждает проблему:** правила проекта требуют не обходить состояние Entity через геттеры/сеттеры и показывают стиль `public private(set)` для VO-свойств; см. `docs/rules.md:34` и `docs/rules.md:37`. Существующая media-сущность следует этому стилю: `app/src/Modules/Media/Domain/Entity/Media.php:43`. Здесь проблема не только в правилах, но и в инкапсуляции состояния доставки.
- **Как исправить:** закрыть запись свойств через `public private(set)` или явные property hooks, а изменение статусов оставить только через доменные методы `markPublishing()`, `markQueued()`, `markHandled()`, `recordJobRetry()`, `markFailed()` и `recordPublishFailure()`.
- **Тесты:** обновить тесты, которые сейчас напрямую меняют `attempts`, и добавить проверку переходов статусов через публичные доменные методы.

### 3. Публичная граница outbox не зафиксирована для Domain-типов

Outbox должен быть модулем, к которому другие части приложения обращаются через Application-слой. Сейчас Application-контракт для добавления события принимает и возвращает типы из Domain-слоя самого outbox. В текущем diff нет внешнего использования этого контракта, поэтому нельзя утверждать, что другие модули уже зависят от `App\Modules\Outbox\Domain`.

Проблема в том, что решение не зафиксировано явно: либо Domain-типы outbox считаются частью публичного контракта модуля, либо публичный Application API должен принимать и возвращать Application-типы. Без этого следующий модуль начнёт использовать контракт по-своему, и границу outbox будет сложнее изменить.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:7` — Application-контракт принимает `OutboxMessage` из Domain
- **Где:** `app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:8` — Application-контракт возвращает `OutboxEventId` из Domain
- **Где:** `app/src/Modules/Outbox/Domain/OutboxMessage.php:7` — marker-интерфейс сообщения лежит в Domain
- **Что подтверждает проблему:** архитектура проекта говорит, что другие модули могут обращаться только к `Application`; см. `docs/arch.md:124`. При этом сам Application-контракт раскрывает Domain-типы.
- **Как исправить:** зафиксировать одно решение. Если Domain-типы outbox являются публичным контрактом, это нужно явно описать в архитектуре модуля. Если нет — вынести публичный тип сообщения в `Application/Outbox` или `Application/Contract`, а возвращаемое значение `add()` заменить на Application-DTO или убрать.
- **Тесты:** обновить тест отправки outbox-события через контракт и проверить выбранную публичную границу модуля.

### 4. Реальный RabbitMQ-путь почти не проверяется

Тесты хорошо проверяют sync-режим и fake queue, но реальный путь через RabbitMQ отличается: payload сериализуется в строку, затем RoadRunner bridge восстанавливает тип payload, и только потом запускается цепочка обработки задачи. Именно в этом месте находится один из самых важных рисков outbox.

Текущий feature-тест принимает сразу два разных формата payload, поэтому он не фиксирует контракт RabbitMQ-transport как единственно правильный. Unit-тест serializer-а проверяет ручной happy path, но не проверяет связку publisher, RoadRunner headers, registry и status-interceptor.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:131` — проверка fake queue допускает разные payload-форматы
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:137` — sync-payload считается допустимым
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:139` — transport-envelope тоже считается допустимым
- **Где:** `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializerTest.php:16` — serializer проверяется отдельно от реального consume path
- **Что подтверждает проблему:** `QUEUE_CONNECTION` для test-runner остаётся `sync`, а RabbitMQ pipeline не поднимается в test-профиле. Поэтому реальная десериализация RoadRunner/RabbitMQ не проходит через feature-тесты.
- **Как исправить:** добавить отдельный интеграционный или узкий компонентный тест для RabbitMQ/RoadRunner пути: publisher создаёт transport payload, bridge/serializer восстанавливает payload, status-interceptor видит outbox headers и корректно меняет статус.
- **Тесты:** новый тест должен падать, если убрать transport keys, сломать `OutboxQueueSerializer::unserialize()` или зарегистрировать Job без выводимого payload-типа.

### 5. Глобальная замена Cycle mapper-а не покрыта прямыми тестами

В diff включён новый mapper для всех Entity проекта. Это низкоуровневое изменение: оно влияет не только на outbox, а на всю гидрацию ORM, lazy relations, чтение `private(set)` свойств и сохранение связей.

Сейчас есть косвенные repository-тесты, но нет прямого теста на сам новый механизм. Если lazy relation, pending reference или извлечение relation-данных сломается, часть сценариев может начать падать в других модулях, а причина будет неочевидной.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/config/cycle.php:25` — `LazyGhostMapper` включён как mapper по умолчанию для схемы Cycle
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:43` — mapper создаёт lazy ghost Entity при инициализации
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:33` — используется Reflection `newLazyGhost`
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:62` — relation reference откладывается в `WeakMap`
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:170` — initializer позже восстанавливает отложенные связи
- **Что подтверждает проблему:** в diff нет отдельного теста для `LazyGhostMapper` или `LazyGhostEntityFactory`; поиск по `LazyGhost` находит только код и старые review/fix-документы, но не тесты. Это риск тестового покрытия, а не прямое доказанное нарушение конкретного правила.
- **Как исправить:** добавить прямые feature-тесты для ORM-гидрации через сущности Media: восстановить Entity с `private(set)` свойствами, проверить lazy `BelongsTo` и `HasMany` до и после первого доступа, затем сохранить изменённую Entity и убедиться, что связи не теряются.
- **Тесты:** отдельный тест должен явно обращаться к relation-свойствам и проверять, что `LazyGhostMapper` не ломает сохранение обычных полей и связей.

### 6. Официальная QA-команда запускает весь suite в режиме coverage

В проект добавлено правило запускать `make qa` после глобальных задач. При этом сама Docker-команда включает coverage сразу для всего `composer qa`, а внутри `composer qa` сначала идут обычные проверки и обычный PHPUnit, и только потом отдельный шаг покрытия.

Из-за этого даже обычный тестовый шаг работает в самом тяжёлом режиме. Команда становится медленной и хрупкой: если она упрётся в стандартный таймаут Composer или просто начнёт занимать слишком много времени, обязательная проверка будет мешать закрывать задачи.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `docker/test/run-qa.sh:8` — вся QA-команда запускается как `XDEBUG_MODE=coverage composer qa`
- **Где:** `composer.json:102` — обычные тесты запускаются через `phpunit`
- **Где:** `composer.json:103` — покрытие запускается отдельным шагом `phpunit --coverage-text`
- **Где:** `composer.json:104` — `qa` последовательно запускает `cs`, `phpstan`, `test`, `test-coverage`
- **Где:** `AGENTS.md:64` — `make qa` добавлен как обязательная проверка
- **Что подтверждает проблему:** coverage включается на весь процесс Composer, хотя покрытие нужно только для отдельного шага `test-coverage`. В ревью нет свежего лога, который доказывает текущее падение `make qa`, поэтому это стоит считать техническим риском и оптимизацией QA-обёртки.
- **Как исправить:** запускать обычные тесты без coverage, а `XDEBUG_MODE=coverage` включать только для шага покрытия. Например, в Docker-скрипте явно выполнить `composer cs`, `composer phpstan`, `composer test`, затем `XDEBUG_MODE=coverage composer test-coverage`.
- **Тесты:** после изменения повторить именно `make qa`, потому что проблема находится в официальной Docker-обёртке.

### 7. Транспортные заголовки очереди вынесены в Application-слой

`OutboxQueueHeaders` лежит в `Application`, но описывает не сценарий outbox, а технический формат заголовков очереди. Сейчас этот тип используется только в `Infrastructure` и `Presentation/Job`, то есть обслуживает транспортный протокол RabbitMQ/RoadRunner.

Это смешивает Application-слой с деталями очереди. Если формат заголовков изменится, изменение будет выглядеть как изменение публичного Application API, хотя по смыслу это внутренняя деталь транспорта.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Outbox/OutboxQueueHeaders.php:7` — тип заголовков лежит в Application
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php:8` — тип используется инфраструктурным publisher-ом
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:7` — тип используется инфраструктурным interceptor-ом
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:8` — тип используется Job-слоем
- **Что подтверждает проблему:** поиск по `OutboxQueueHeaders` показывает только технические места очереди и тесты вокруг них, а не Application-сценарии.
- **Как исправить:** вернуть тип в инфраструктурный слой очереди или выделить Application-тип только для настоящего публичного контракта, без transport-деталей.
- **Тесты:** обновить текущие тесты, которые импортируют `OutboxQueueHeaders`, чтобы они проверяли тот слой, где этот тип действительно должен жить.

### 8. Сценарий `outbox:relay` записывает Entity вне Handler-а

Правила проекта требуют, чтобы Handler явно управлял сохранением Entity через `persist()` / `delete()` и `run()`. В текущем сценарии Handler только делегирует вызов worker-у, а `EntityManager::run()` вызывается внутри инфраструктурного relay.

Из-за этого граница записи состояния outbox становится неочевидной: Application-сценарий выглядит тонким, но фактические изменения Entity и flush происходят глубже, в инфраструктурном сервисе. Это расходится с текущим правилом проекта про место записи.

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Command/Outbox/RelayOutbox/RelayOutboxHandler.php:15` — Handler делегирует loop-сценарий worker-у
- **Где:** `app/src/Modules/Outbox/Application/Command/Outbox/RelayOutbox/RelayOutboxHandler.php:21` — Handler делегирует разовый запуск worker-у
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:89` — `EntityManager::run()` вызывается в инфраструктурном relay при claim
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:136` — `EntityManager::run()` вызывается в инфраструктурном relay при ошибке публикации
- **Что подтверждает проблему:** `docs/rules.md:80` требует, чтобы `entityManager->run()` оставался внутри Handler-а.
- **Как исправить:** либо перенести управление flush в Application-сценарий, либо явно обновить архитектурное правило для outbox relay как исключения-инфраструктурного orchestrator-а.
- **Тесты:** после выбранного решения оставить feature-тесты на `outbox:relay`, чтобы статусные переходы сохранялись в БД в тех же сценариях.

### 9. Разбор queue-заголовков продублирован в Job и interceptor-е

Одинаковая логика превращения raw headers в строку живёт в `OutboxQueueStatusInterceptor` и `OutboxDebugLogJob`. Сейчас оба метода выглядят одинаково, но это уже две точки правки для одного transport-формата.

Если RoadRunner или RabbitMQ начнут отдавать заголовки в другом виде, один метод можно обновить, а второй забыть. Тогда статус outbox и реальные Job начнут по-разному понимать один и тот же набор заголовков.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:109` — первый `headerLine()`
- **Где:** `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:39` — второй `headerLine()`
- **Как исправить:** оставить разбор raw headers в одном техническом helper-е или объекте на границе очереди, а дальше передавать уже нормализованные строки.
- **Тесты:** покрыть разбор строки, массива строк, пустого значения и отсутствующего заголовка одним набором тестов.

### 10. `LazyGhostEntityFactory` слишком много делает для глобального mapper-а

`LazyGhostEntityFactory` одновременно создаёт lazy-object, гидрирует обычные поля, откладывает relation reference, восстанавливает relation при инициализации и извлекает данные обратно для Cycle. Для класса, который включён глобально в ORM mapper, это повышает цену сопровождения.

Риск не в текущем падении, а в будущих изменениях: правка восстановления связей может случайно затронуть извлечение обычных полей или наоборот. С учётом отсутствия прямых тестов на mapper это делает регрессии менее заметными.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:30` — создание lazy-object
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:42` — гидрация Entity и relation reference
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:117` — извлечение обычных данных
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:139` — извлечение relation-данных
- **Где:** `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php:170` — initializer восстанавливает отложенные связи
- **Как исправить:** не делать большой рефакторинг отдельно от тестов; если mapper продолжит расти, разделить создание lazy-object, работу с pending relations и извлечение данных на небольшие классы.
- **Тесты:** сначала добавить прямые тесты из замечания 5, а уже потом безопасно дробить ответственность.

## Рекомендации

- **Править обязательно:** 1, 2, 3, 4, 7, 8
- **На усмотрение автора:** 5, 6, 9, 10

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** проблема с `OutboxQueueHeaders` в Application-слое; проблема с `EntityManager::run()` вне Handler-а в сценарии `outbox:relay`; quality-замечания про дублирование разбора queue-заголовков и перегруженный `LazyGhostEntityFactory`.
- **~ Изменено:** проверка плана теперь записана как непроверенная без пути к плану; пункт про Domain-типы outbox переформулирован как незафиксированная публичная граница; пункт про открытые свойства Entity уточнён как `rules` и `quality`; замечания про `LazyGhostMapper` и coverage в `make qa` переведены в рекомендации на усмотрение автора.
- **− Убрано:** утверждение, что внешние модули уже вынуждены импортировать `App\Modules\Outbox\Domain`; утверждение, что coverage-обёртка уже доказанно ломает `make qa`.
- **Отклонено:** замечание про зависимости Cycle attributes Entity от Infrastructure/Repository не добавлялось, потому что это существующая локальная практика; замечание про Query Builder в Repository не добавлялось, потому что текущие методы используют его для атомарного update и свежего чтения, где причина обхода ORM понятна из сценария; фактические результаты `make test` и `make phpstan` не добавлялись, потому что эти команды не запускались в этой проверке ревью.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-05-28_13-40_outbox-rabbitmq-uncommitted-draft.md`
