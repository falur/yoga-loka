---
title: Модуль уведомлений
date: 2026-06-13 12:58
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль уведомлений

## Суть

Исследовали полный модуль уведомлений для YogaLoka: хранение уведомлений в базе,
push-уведомления через FCM и сообщения в Centrifugo для запущенного приложения.
Пользователь должен управлять тем, какие виды уведомлений уходят в какие каналы.

Проект — API-first backend на Spiral, RoadRunner, PostgreSQL и Cycle ORM
(`docs/arch.md:5`). Код устроен как модульный монолит, где модули общаются через
Application-слой, а не через чужие репозитории или таблицы (`docs/arch.md:8`,
`docs/arch.md:131`). Внешние эффекты, включая Centrifugo и push, должны идти через
transactional outbox после успешного commit-а (`docs/arch.md:514`,
`docs/rules.md:86`). Поэтому уведомления нельзя отправлять напрямую из модуля
чатов, записей или другого бизнес-модуля.

Готовый результат исследования — выбранная архитектура модуля `Notifications`,
схема базы данных и API для других модулей и мобильного приложения.

## Решение

Выбранный вариант: модуль `Notifications` принимает запросы от других модулей
через свой Application-контракт, сохраняет запрос в outbox в той же транзакции, что
и бизнес-действие, затем асинхронно создаёт уведомление и отдельно отправляет его в
нужные каналы. Push-канал реализуется через FCM HTTP v1.

Этот вариант выбран, потому что в проекте уже есть outbox-модуль для безопасных
действий после изменения данных (`app/src/Modules/Outbox/README.md:3`,
`app/src/Modules/Outbox/README.md:15`). Outbox уже хранит события в
`outbox_events`, relay перекладывает pending-события в очередь, а Job выполняет
действие после commit-а (`app/src/Modules/Outbox/README.md:36`). Такой же паттерн
уже используется в `Media`: Handler кладёт `MediaUploaded` в outbox внутри
транзакции (`app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:40`,
`app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:64`),
а Job потом загружает сообщение и запускает Application-команду
(`app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:41`,
`app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:47`).

Основной поток:

```text
Модуль-источник: Chat / Feed / Comment / ...
  -> свой Transactional Handler меняет свои данные
  -> Notifications\Application\Contract\NotificationRequestPublisherContract::request()
  -> реализация кладёт NotificationRequested в outbox
  -> EntityManager::run()

Outbox relay
  -> CreateNotificationJob
  -> CreateNotificationCommand
  -> Notifications создаёт Notification и Delivery-строки
  -> для push и Centrifugo кладёт отдельные delivery-события в outbox

Outbox relay
  -> SendPushNotificationJob / PublishCentrifugoNotificationJob
  -> FCM HTTP v1 или Centrifugo HTTP API
  -> delivery получает sent / failed / skipped
```

Прямой вызов FCM или Centrifugo из исходного бизнес Handler-а не используется.
Риск: уведомление появится не мгновенно, а после relay и очереди. Это принимается:
зато основное действие пользователя не ломается из-за временного сбоя FCM,
Centrifugo или RabbitMQ, и внешний эффект не произойдёт раньше commit-а
(`docs/arch.md:527`).

### Границы модуля

| Часть | Решение | Источник / причина |
|---|---|---|
| Модуль | `App\Modules\Notifications` | Новые предметные области оформляются отдельными модулями (`docs/arch.md:23`). |
| Вход от других модулей | Application-контракт `NotificationRequestPublisherContract` | Другие модули могут обращаться только к Application-слою (`docs/arch.md:131`). |
| Очередь и надёжность | Outbox + RabbitMQ/RoadRunner jobs | Dev runtime использует RabbitMQ как основную очередь (`docs/arch.md:178`). |
| Каналы | Расширяемые channel-code через registry, базовые: `database`, `push`, `centrifugo` | Пользователь просит расширяемые типы; enum не подходит для открытого набора (`docs/rules.md:30`). |
| Виды уведомлений | Расширяемые kind-code через registry, например `chat.message_created`, `feed.comment_created` | Виды должны добавляться из новых модулей без изменения ядра уведомлений. |
| Пользовательские настройки | Override-таблица по `userId + kind + channel`; если строки нет, берётся default из kind registry | Нужна настройка “какой вид в какой тип”. |
| Реальное время | Centrifugo HTTP API, канал пользователя | Проект уже поднимает Centrifugo v6.7.2 (`docker/docker-compose.dev.yml:220`). |
| Push | FCM HTTP v1 | Пользователь выбрал FCM; FCM HTTP v1 использует OAuth 2.0 access token по официальной документации Firebase: https://firebase.google.com/docs/cloud-messaging/send/v1-api |

### Почему channel и kind не enum

В правилах проекта enum нужен для закрытого набора значений (`docs/rules.md:30`).
Здесь пользователь прямо указал, что типы уведомлений должны расширяться. Поэтому
`kind` и `channel` лучше хранить как ValueObject со строковым кодом и проверкой
формата, а доступные значения регистрировать в коде через registry. Это позволяет
добавить, например, канал `email` или вид `subscription.expired` без изменения
базовой доменной модели.

Риск: строковый код можно написать с ошибкой. Закрытие риска: все коды проходят
через `NotificationKindRegistry` и `NotificationChannelRegistry`; неизвестный код
падает как ошибка разработки, а не молча сохраняется.

### Виды уведомлений

Каждый вид уведомления описывается definition-классом:

```text
NotificationKindDefinition
  kind: chat.message_created
  defaultChannels: database, push, centrifugo
  titleTranslationKey: app.notifications.chat.message_created.title
  bodyTranslationKey: app.notifications.chat.message_created.body
  payloadSchema: список поддерживаемых текстовых параметров
```

Модуль, который добавляет новый вид, регистрирует definition в своём bootloader-е.
Например, будущий модуль `Chat` регистрирует `chat.message_created`, а будущий
модуль `Feed` регистрирует `feed.comment_created`. Это соответствует правилу, что
Outbox не должен зависеть от чужой предметной логики; пары message -> Job
регистрируются в bootloader-е владельца (`app/src/Modules/Outbox/README.md:324`).

Текст уведомления хранится не готовой строкой, а ключами перевода и типизированным
списком параметров:

```text
NotificationTextParameter
  name: actorName
  value: Анна
```

Так не нужен `array<string, mixed>`, который запрещён публичными контрактами
(`docs/rules.md:98`, `docs/rules.md:100`). API и push могут отдать текст на языке
пользователя. HTTP-локаль в проекте уже определяется из `Accept-Language`
(`app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:38`),
а для queue/console per-request локали нет (`docs/arch.md:384`), поэтому push
должен брать локаль из будущего профиля пользователя. Если профиля ещё нет, на
первом этапе используется default locale приложения. Это гипотеза, потому что
модуля пользователя в текущем коде ещё нет.

### База данных

Схема хранит само уведомление, доставки по каналам, настройки пользователя и FCM
токены устройств.

| Таблица | Назначение | Ключевые поля |
|---|---|---|
| `notifications` | Событие уведомления как внутренний источник правды | `id uuid`, `recipient_user_id uuid`, `kind varchar`, `source_event_id uuid`, `title_key varchar`, `body_key varchar`, `text_params jsonb`, `action_type varchar`, `action_id varchar`, `created_at`, `updated_at` |
| `notification_deliveries` | Состояние доставки по каждому каналу | `id uuid`, `notification_id uuid`, `channel varchar`, `status varchar`, `attempts int`, `last_error text`, `sent_at`, `failed_at`, `created_at`, `updated_at` |
| `notification_preferences` | Пользовательские overrides | `id uuid`, `user_id uuid`, `kind varchar`, `channel varchar`, `status varchar`, `created_at`, `updated_at` |
| `notification_device_tokens` | Устройства для push | `id uuid`, `user_id uuid`, `provider varchar`, `platform varchar`, `token_ciphertext text`, `token_hash varchar`, `status varchar`, `last_seen_at`, `revoked_at`, `created_at`, `updated_at` |

Индексы:

```text
notifications:
  primary key (id)
  unique (source_event_id)
  index (recipient_user_id, id)
  index (recipient_user_id, kind, id)

notification_deliveries:
  primary key (id)
  unique (notification_id, channel)
  index (channel, status, id)

notification_preferences:
  primary key (id)
  unique (user_id, kind, channel)

notification_device_tokens:
  primary key (id)
  unique (provider, token_hash)
  index (user_id, status, id)
```

UUID должен быть v7, потому что это правило проекта для всех идентификаторов
(`docs/rules.md:31`). Пагинация списка уведомлений должна идти по `id`, потому что
правила проекта используют cursor-пагинацию по UUID v7 (`docs/rules.md:32`).

`text_params` хранится как JSONB, но в коде это не ассоциативный массив, а
типизированная коллекция `NotificationTextParameterCollection` с отдельным
typecast-классом. Для JSON и коллекций правила проекта требуют отдельный typecast
(`docs/rules.md:81`).

FCM-токен нельзя хранить только открытым текстом. Рекомендация: хранить
`token_hash` для поиска дублей и `token_ciphertext` для отправки. Риск утечки
токенов снижается, но не исчезает: доступ к таблице всё равно должен быть ограничен.
FCM официально рекомендует управлять registration tokens и удалять устаревшие или
невалидные токены: https://firebase.google.com/docs/cloud-messaging/manage-tokens

### API для других модулей

Другие модули не пишут в таблицы `notifications` напрямую и не знают про FCM или
Centrifugo. Их публичная точка входа:

```php
NotificationRequestPublisherContract::request(NotificationRequest $notificationRequest): StoredOutboxEventId
```

`NotificationRequest` — Application DTO с примитивами и типизированными DTO:

```text
requestId: string UUID v7
recipientUserId: string UUID
kind: string
titleKey: string
bodyKey: string
textParameters: list<NotificationTextParameter>
actionType: string
actionId: string
occurredAt: string ISO-8601
```

`requestId` нужен для идемпотентности. Если модуль чатов повторит тот же запрос,
`Notifications` не создаст дубль. Риск: если источник не передаст стабильный
`requestId`, появятся дубликаты. Закрытие риска: контракт должен требовать
`requestId`, а для типовых событий он строится из id исходного события, например
`messageId` или `commentId`.

Пример использования будущим модулем чатов:

```text
SendChatMessageHandler
  -> сохраняет Message
  -> NotificationRequestPublisherContract::request(
       requestId = messageId,
       recipientUserId = chatParticipantId,
       kind = chat.message_created,
       textParameters = actorName + chatTitle,
       actionType = chat,
       actionId = chatId
     )
  -> EntityManager::run()
```

### HTTP API для мобильного приложения

Контроллеры должны быть тонкими, использовать Filter для входящих данных и
возвращать типизированные Response-классы (`docs/rules.md:66`, `docs/rules.md:81`,
`docs/rules.md:98`). Идентификатор пользователя берётся из request attribute
`authUserId` (`docs/rules.md:69`).

Публичные endpoint-ы модуля:

```text
GET    /api/v1/notifications
       Список уведомлений пользователя, cursor по id.

POST   /api/v1/notifications/{id}/read
       Пометить одно уведомление прочитанным.

POST   /api/v1/notifications/read-all
       Пометить все видимые database-уведомления прочитанными.

GET    /api/v1/notification-preferences
       Вернуть все виды, каналы, defaults и пользовательские overrides.

PUT    /api/v1/notification-preferences/{kind}/{channel}
       Включить или выключить канал для вида уведомления.

POST   /api/v1/notification-device-tokens
       Зарегистрировать или обновить FCM-токен устройства.

DELETE /api/v1/notification-device-tokens/{id}
       Отозвать токен устройства.

POST   /api/v1/notifications/realtime/token
       Выдать токен подключения к пользовательскому каналу Centrifugo.
```

GET `/api/v1/notifications` возвращает только те уведомления, где канал `database`
включён и delivery не `skipped`. Если пользователь выключил database, но оставил
push, push может прийти без появления в списке уведомлений. Это соответствует
настройке “какой вид уведомления пойдёт в какой тип”.

### Delivery-логика

При создании уведомления модуль читает default-каналы из kind definition и
пользовательские overrides из `notification_preferences`.

```text
enabled channel database
  -> delivery status sent
  -> уведомление видно в GET /api/v1/notifications

enabled channel centrifugo
  -> delivery status pending
  -> outbox SendCentrifugoNotificationRequested

enabled channel push
  -> delivery status pending
  -> outbox SendPushNotificationRequested

disabled channel
  -> delivery status skipped
```

Если у пользователя нет активных FCM-токенов, push delivery становится `skipped`.
Если FCM отвечает, что токен невалиден, токен переводится в `revoked`, а delivery
по этому токену не повторяется. Если FCM временно недоступен, Job бросает retry,
а outbox обрабатывает повтор по уже существующим правилам (`app/src/Modules/Outbox/README.md:50`,
`app/src/Modules/Outbox/README.md:461`).

### FCM

Для push используется FCM HTTP v1. Официальная документация Firebase описывает
отправку через HTTP v1 и OAuth 2.0 access token:
https://firebase.google.com/docs/cloud-messaging/send/v1-api

Для PHP-токена OAuth рекомендуется пакет `google/auth`. Это официально
поддерживаемая Google PHP-библиотека для OAuth 2.0; актуальная стабильная версия
на дату проверки 2026-06-13 — `1.50.1`:
https://docs.cloud.google.com/php/docs/reference/auth/latest

Не выбран полный `google/apiclient`, потому что модулю нужен только OAuth-токен и
обычный HTTP-запрос к FCM. Полный клиент шире и добавляет лишние зависимости.
Риск: FCM требует секрет service account. Закрытие риска: секрет хранится только в
env/config, код получает его через typed config, потому что `env()` разрешён только
в `app/config/*.php` (`docs/rules.md:60`).

### Centrifugo

Для уведомлений в запущенное приложение используется собственный HTTP-клиент к
Centrifugo. Это прямо требуется правилами проекта: пакет `centrifugal/phpcent`
удалён, а взаимодействие с Centrifugo должно идти через свой HTTP-клиент
(`docs/rules.md:85`). Centrifugo Server API официально поддерживает HTTP API для
публикации сообщений в каналы:
https://centrifugal.dev/docs/server/server_api

Канал пользователя:

```text
notifications:user:{userId}
```

Сообщение в Centrifugo:

```text
notificationId
kind
title
body
actionType
actionId
createdAt
```

Риск: если приложение не подключено к Centrifugo, realtime-сообщение не будет
получено. Это нормально для канала `centrifugo`: он нужен только “когда приложение
запущено”. Для последующего чтения отвечает канал `database`, если пользователь
его включил.

### Сравнение закрытых развилок

| Развилка | Выбранный вариант | Почему |
|---|---|---|
| Создание уведомлений | Асинхронно через outbox | Совпадает с проектным правилом внешних эффектов после commit-а (`docs/rules.md:86`). |
| Push-провайдер | FCM HTTP v1 | Пользователь выбрал FCM; один серверный API покрывает Android и iOS при настройке мобильного клиента. |
| Расширение видов | Registry + string ValueObject, не enum | Виды должны добавляться новыми модулями. |
| Расширение каналов | Registry + string ValueObject, не enum | Каналы тоже должны расширяться. |
| Хранение пользовательских настроек | Только overrides в БД, defaults в коде kind definition | Новые виды появляются без миграции настроек для всех пользователей. |
| Синхронная отправка Centrifugo/FCM | Не используется | Сбой внешнего сервиса не должен ломать создание сообщения или комментария. |

## Ответы на вопросы

Вопрос: какой поток создания уведомлений и какой push-провайдер выбрать?

Ответ пользователя: `1. Асинхронно через outbox + FCM для push`.

Это решение зафиксировано как основа архитектуры.

## Итог

Дальше модуль стоит проектировать как `Notifications` с четырьмя главными
сущностями: `Notification`, `NotificationDelivery`, `NotificationPreference` и
`NotificationDeviceToken`. Другие модули должны обращаться только к
`NotificationRequestPublisherContract`, а не к таблицам уведомлений и не к FCM.

Виды уведомлений и каналы должны быть расширяемыми через registry и строковые
ValueObject-коды. Пользовательские настройки хранятся как overrides по
`userId + kind + channel`, а defaults живут в definition-классах видов
уведомлений.

Внешняя отправка делится на два outbox-этапа: сначала создать уведомление и
delivery-строки, затем отдельно отправить push и Centrifugo. Это сохраняет данные
в базе надёжно и не ломает основное действие пользователя при сбое внешних
сервисов.
