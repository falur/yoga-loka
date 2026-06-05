---
title: Ревью незакоммиченных изменений outbox RabbitMQ после фиксов
date: 2026-05-28 13:47
target: git diff HEAD + untracked files
mode: normal
score: 76
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ после фиксов

## Оценка

**76/100.** Основные замечания предыдущего ревью закрыты: состояние outbox стало лучше защищено, payload восстанавливается внутри Job, добавлены проверки sync и transport-сценариев. Остаются риски в аварийных сценариях: часть старых или неправильно зарегистрированных сообщений всё ещё может сломаться до места, где outbox умеет записать ошибку. После мета-ревью добавлено одно замечание на усмотрение автора по поддерживаемости outbox-тестов.

## Проблемы сверки с планом

План не проверен: нужен путь к файлу плана. Ревью было запрошено без плана, поэтому plan-check не пытался угадывать исходные требования.

## Замечания

### 1. Документация показывает порядок, при котором outbox-событие можно не сохранить

В архитектурном описании Handler сначала сохраняет основные изменения, а потом добавляет событие в outbox. Но текущий outbox-store только ставит событие на сохранение и сам не делает запись в базу.

Если следующий разработчик повторит порядок из документа, бизнес-изменение будет сохранено, а внешнее событие может остаться только в памяти процесса. Тогда письмо, push, webhook или другое внешнее действие не будет отправлено, хотя основной сценарий уже завершился.

Технические детали:

- **Тип:** `docs`, `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `docs/arch.md:472` — схема потока outbox-события
- **Где:** `docs/arch.md:477` — `OutboxEventStoreContract::add(...)` указан после `EntityManager::run()`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxEventStore.php:30` — `add()` вызывает только `persist()`
- **Что подтверждает проблему:** `OutboxEventStore::add()` не вызывает `EntityManager::run()`, а правила проекта требуют явного flush в Handler-е. Значит событие нужно добавить до финального `run()` или сразу описать второй `run()` после добавления события.
- **Как исправить:** в `docs/arch.md` поменять порядок: Handler собирает доменные изменения, добавляет outbox-событие, затем один раз вызывает `EntityManager::run()` внутри той же транзакции. Если выбран другой контракт, явно написать, что после `OutboxEventStoreContract::add()` нужен отдельный `run()`.
- **Тесты:** добавить или обновить feature-тест Application-сценария, где Handler сохраняет доменную сущность и outbox-событие в одной транзакции, а после dispatch обе записи есть в базе.

### 2. Старый тип outbox-сообщения может сломать relay и consumer до записи ошибки

Outbox хранит тип сообщения как имя PHP-класса. Сейчас это имя проверяется уже при чтении строки из базы или transport payload. Если класс был переименован или удалён после деплоя, событие может упасть при восстановлении Entity в relay или при восстановлении transport envelope в consumer, ещё до кода, который умеет перевести его в `failed` или записать причину.

Для outbox это опасный сценарий: именно старые сообщения часто остаются в базе или очереди между релизами. Вместо понятного статуса ошибки relay или consumer может просто падать на одном и том же событии.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxEventType.php:17` — `fromString()` требует `class_exists()`
- **Где:** `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:35` — поле `type` восстанавливается через этот value object при гидрации Entity
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueEnvelope.php:36` — transport envelope тоже восстанавливает тип через `OutboxEventType::fromString()`
- **Где:** `app/src/Modules/Outbox/Repository/OutboxEventRepository.php:44` — relay читает пачку через ORM Select, где ошибка typecast остановит выборку
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:44` — consumer читает событие до `try`, который записывает ошибку Job
- **Что подтверждает проблему:** ошибка `class_exists()` произойдёт раньше `OutboxRelay::publish()` при чтении Entity и раньше `OutboxQueueStatusInterceptor::process()` при десериализации transport payload. Значит outbox не сможет сохранить `last_error` для старого типа сообщения.
- **Как исправить:** хранить `OutboxEventType` как непустую строку без проверки существования класса при чтении из базы и transport payload. Проверку класса и интерфейса делать позже, в serializer или registry, уже внутри участка кода, где известен `outboxId` и можно записать `failed` или publish-failure.
- **Тесты:** добавить тест с записью `outbox_events.type = 'Old\\Removed\\Message'` через query builder и проверить, что relay или queue consumer не падает бесконечно, а переводит событие в ошибочное состояние с заполненным `last_error`.

### 3. Serializer падает до interceptor, если RoadRunner не смог вывести payload-класс

Для RabbitMQ payload кладётся в очередь как массив, поэтому RoadRunner не добавляет заголовок с классом payload. На обработке класс payload должен быть выведен из зарегистрированного Job handler-а. Если Job-класс недоступен после переименования, handler не найден или его `invoke()` больше не содержит параметр `payload` с типом `OutboxQueueEnvelope`, serializer получает `null` вместо `OutboxQueueEnvelope::class` и бросает исключение до запуска outbox-interceptor.

В таком случае событие уже может быть в статусе `queued`, но consumer не дойдёт до кода, который записывает `failed`. Это особенно неприятно при добавлении новых реальных outbox Job: ошибка регистрации превращается не в понятную запись в outbox, а в зависшее сообщение.

Технические детали:

- **Тип:** `bug`, `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php:71` — payload для не-sync очереди собирается как массив
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php:79` — в RabbitMQ уходит transport array, а не объект envelope
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:24` — serializer требует ровно `OutboxQueueEnvelope::class`
- **Где:** `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php:25` — при другом или пустом типе serializer падает
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php:104` — тест проверяет успешное восстановление, когда тип явно передан
- **Где:** `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Queue.php:98` — `payload_class` добавляется только для object payload
- **Где:** `vendor/spiral/roadrunner-bridge/src/Queue/Internal/PayloadDeserializer.php:54` — если тип не найден, serializer вызывается без класса payload
- **Где:** `vendor/spiral/roadrunner-bridge/src/Queue/Internal/Dispatcher.php:65` — десериализация payload происходит до передачи задачи в обработчик очереди
- **Что подтверждает проблему:** outbox-interceptor работает уже после десериализации payload очереди. Если serializer падает на этапе восстановления envelope, `OutboxQueueStatusInterceptor::process()` не получает управление и не может обновить статус события.
- **Как исправить:** сделать transport serializer устойчивым к отсутствующему `$type` и по умолчанию восстанавливать `OutboxQueueEnvelope`, либо отправлять envelope так, чтобы RoadRunner всегда сохранял `payload_class`. Отдельно стоит централизовать регистрацию outbox Job в одном месте, чтобы `OutboxJobRegistry`, queue handler registry и serializer не расходились.
- **Тесты:** добавить тест, который вызывает `OutboxQueueSerializer::unserialize($payload, null)` для валидного outbox transport payload и проверяет восстановление envelope. Добавить feature-тест ошибки регистрации Job: событие не должно оставаться в `queued` без `last_error`.

### 4. Outbox feature-тесты стали перегруженными

Два outbox feature-теста теперь одновременно проверяют много сценариев и держат тестовые сообщения, Job, fake queue и Core-объекты прямо в тех же файлах. Это пока не ломает поведение, но усложняет поддержку: новое изменение relay или interceptor-а придётся читать через большие файлы с разными уровнями абстракции.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:34` — основной тестовый класс занимает 496 строк вместе с fake-классами
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:365` — тестовые message/job/queue-классы объявлены в том же файле
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php:31` — interceptor-тест занимает 350 строк
- **Где:** `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php:296` — тестовые `CoreInterface`-реализации объявлены в том же файле
- **Что подтверждает проблему:** сценарии sync relay, transport payload, ошибки публикации, retry, status-interceptor и Job-core находятся в крупных файлах с локальными fake-классами. Это повышает стоимость правок и риск случайно сломать соседний сценарий при добавлении нового случая.
- **Как исправить:** вынести общие fake-классы и helper-ы в отдельные тестовые файлы или fixtures, а сценарии relay и interceptor-а разделить на более узкие test-классы.
- **Тесты:** после разделения оставить те же проверки поведения; это реорганизация тестового кода, а не изменение production-логики.

## Рекомендации

- **Править обязательно:** 1, 2, 3
- **На усмотрение автора:** 4

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** quality-замечание про перегруженные outbox feature-тесты.
- **~ Изменено:** проверка плана теперь записана как непроверенная без пути к плану; пункт 2 уточняет, что старый тип может ломать и relay, и consumer; пункт 3 уточняет, что проблема возникает, когда RoadRunner не смог вывести класс payload до запуска interceptor-а; оценка снижена с 78 до 76.
- **− Убрано:** нет.
- **Отклонено:** architecture-check не предложил изменений; отдельный пункт про неизвестный запуск `make test`, `make phpstan` и `make qa` не добавлен, потому что в мета-проверке эти команды не запускались, а в diff и тексте ревью нет логов, по которым можно честно проверить их выполнение.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-05-28_14-21_outbox-rabbitmq-uncommitted-draft.md`
