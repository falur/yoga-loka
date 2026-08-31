---
title: Outbox через RabbitMQ
date: 2026-05-24 17:25
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Outbox через RabbitMQ

## Суть

Исследовали, как сделать transactional outbox для внешних действий YogaLoka:
Centrifugo, email, push и webhooks. В проекте это уже задано правилом:
внешние события сохраняются в outbox в той же транзакции, что и бизнес-изменение,
а прямые вызовы внешних сервисов из Handler-ов запрещены (`docs/rules.md:72`,
`docs/arch.md:437`).

Нужно было выбрать, делать ли outbox отдельным пакетом или внутри проекта, кто
должен выполнять события, нужна ли Kafka, Ecotone или RabbitMQ, и как не
размазывать изменение статусов outbox по каждой задаче очереди.

## Решение

Выбран вариант: **outbox внутри проекта + RabbitMQ как основная очередь +
центральный слой статусов outbox вокруг Spiral Queue Job**.

Схема:

```text
Application Handler
  -> сохраняет бизнес-данные
  -> сохраняет outbox_events в PostgreSQL
  -> commit

OutboxRelay
  -> берёт pending outbox_events
  -> кладёт задачу в RabbitMQ через Spiral Queue
  -> добавляет outboxId в headers и payload-envelope
  -> ставит outbox status = queued

RoadRunner jobs consumer
  -> забирает задачу из RabbitMQ
  -> запускает обычный Spiral Job Handler
  -> общий consume interceptor ставит handled / failed
```

Сами Job Handler-ы не знают про outbox:

```text
SendPushJob
  -> отправляет push

SendEmailJob
  -> отправляет email

PublishCentrifugoJob
  -> публикует сообщение
```

Статус outbox меняет не каждый Job, а один общий слой:

```text
OutboxQueueStatusInterceptor
  -> читает outboxId из headers
  -> после успешной обработки ставит handled
  -> при ошибке фиксирует failed attempt
  -> пробрасывает ошибку дальше, чтобы RabbitMQ/RoadRunner сделали retry или nack
```

Почему это подходит проекту:

| Факт | Что это значит |
|---|---|
| Проект - модульный монолит на Spiral, RoadRunner и Cycle ORM (`docs/arch.md:1`, `docs/arch.md:5`) | Не нужен тяжёлый внешний framework для messaging |
| Outbox уже описан как часть архитектуры (`docs/arch.md:437`) | Решение не меняет направление архитектуры |
| Текущая очередь `in-memory` (`app/config/queue.php:21`, `docker/rr/http-jobs.yaml:39`) | Для важных событий её надо заменить |
| В конфиге уже импортирован `AMQPCreateInfo` (`app/config/queue.php:6`) | RoadRunner/Spiral уже готовы к AMQP-конвейеру |
| `spiral/roadrunner-jobs` установлен в версии `v4.7.0` (`composer.lock:6722`) | Текущий стек поддерживает RoadRunner jobs |
| `AMQPCreateInfo` содержит настройки `queue`, `exchange`, `routingKey`, `prefetch`, `durable`, `requeueOnFail` (`vendor/spiral/roadrunner-jobs/src/Queue/AMQPCreateInfo.php:39`) | RabbitMQ можно подключить через существующий RoadRunner jobs механизм |
| Spiral Queue поддерживает headers в `Options` (`vendor/spiral/framework/src/Queue/src/Options.php:96`) | `outboxId` можно передавать без изменения каждого Job |
| RoadRunner подтверждает задачу после успешного handler-а (`vendor/spiral/roadrunner-bridge/src/Queue/Internal/Dispatcher.php:68`) | Статус `handled` можно ставить до ack, в общем interceptor-е |

RabbitMQ добавляется сразу в Docker-разработку:

```text
docker/docker-compose.dev.yml
  -> service rabbitmq
  -> image rabbitmq:4.3.0-management-alpine
  -> ports 5672 и 15672 на host prefix 6
  -> volume rabbitmq-data
  -> healthcheck

app-http
  -> depends_on rabbitmq healthy

app/config/queue.php
  -> default queue connection = rabbitmq/amqp
  -> pipeline amqp через AMQPCreateInfo
```

Версия RabbitMQ: на 2026-05-24 официальный Docker Hub показывает
`rabbitmq:4.3.0-management-alpine` как доступный официальный tag. Management
вариант нужен в разработке для UI. Floating tags вроде `latest` не использовать,
чтобы локальный runtime не менялся сам по себе
(https://hub.docker.com/_/rabbitmq/tags?name=-management-alpine&page=1).

RabbitMQ выбран вместо SQS и Kafka:

| Вариант | Почему не выбран / выбран |
|---|---|
| RabbitMQ / AMQP | Выбран. Это очередь задач: 50 worker-ов могут читать одну очередь, есть ack/nack, retry, dead-letter, management UI. RabbitMQ docs описывают durable queues и acknowledgement модель (https://www.rabbitmq.com/docs/4.2/queues, https://www.rabbitmq.com/docs/reliability). |
| SQS | Хороший вариант, если production точно в AWS. Сейчас это преждевременная привязка к провайдеру. |
| Kafka | Хороша для журнала событий, микросервисов, аналитики и replay. Для текущего монолита и задач email/push/Centrifugo слишком тяжёлая. |
| Ecotone | Технически умеет outbox, но тянет свой CommandBus и messaging-слой. Это конфликтует с текущей архитектурой CQRS и Spiral Queue. |

Ключевой риск выбранной схемы: RabbitMQ и PostgreSQL не имеют общей транзакции.
Если `OutboxRelay` успешно положил сообщение в RabbitMQ, но упал до `queued`,
сообщение может быть положено повторно. Это принимается как нормальное свойство
схемы. Закрытие риска: каждое сообщение имеет `outboxId`, Job Handler-ы должны
быть идемпотентными, а общий interceptor перед обработкой может пропускать
сообщение, если outbox уже в финальном статусе.

Ещё один риск: Job выполнил внешнее действие, но запись `handled` не успела
сохраниться. RoadRunner тогда не подтвердит задачу, и она может повториться.
Закрытие такое же: `outboxId` и идемпотентность внешних действий. Для email,
push, Centrifugo и webhooks использовать `outboxId` как ключ идемпотентности там,
где это возможно.

В отдельный Composer-пакет outbox сейчас не выносить. Причина: контракт ещё не
проверен на реальных событиях проекта. Сначала сделать внутри `App\Shared` или
отдельного технического модуля приложения, после 2-3 реальных сценариев можно
выделить маленький `tools/outbox`.

## Ответы на вопросы

| Вопрос / развилка | Ответ |
|---|---|
| Делать outbox отдельным пакетом сразу? | Нет. Сначала внутри проекта, чтобы не делать преждевременную абстракцию. |
| Должен ли outbox сам выполнять события? | Нет. Outbox хранит событие, `OutboxRelay` перекладывает его в RabbitMQ, Job выполняет действие. |
| Нужно ли вручную менять статус outbox в каждом Job? | Нет. Статус меняет общий queue interceptor по `outboxId`. |
| Подходит ли Ecotone? | Нет для текущего проекта: слишком большой слой и свой CommandBus. |
| Берём Kafka? | Нет. Kafka больше подходит для микросервисов, event streaming, аналитики и replay. |
| Какую очередь выбираем? | RabbitMQ / AMQP. |
| Добавляем ли RabbitMQ в Docker-разработку сразу? | Да. RabbitMQ должен стать частью локального dev runtime, а не опциональной внешней зависимостью. |

## Итог

Дальше планировать реализацию так: добавить RabbitMQ в Docker, заменить
`in-memory` очередь на AMQP-пайплайн RoadRunner, сделать outbox-таблицу,
`OutboxRelay`, общий `OutboxQueueStatusInterceptor` и первый реальный Job. Outbox
делать внутри проекта; отдельный пакет не создавать на первом этапе.
