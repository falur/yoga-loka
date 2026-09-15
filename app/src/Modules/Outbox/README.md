# Модуль Outbox

Модуль Outbox нужен для безопасной отправки внешних действий после изменения
данных в базе.

Пример внешних действий:

- отправить задачу в очередь;
- отправить email;
- отправить push;
- отправить webhook;
- отправить событие в Centrifugo.

Главная идея простая: бизнес-сценарий не вызывает внешний сервис напрямую.
Вместо этого он сохраняет outbox-событие в таблицу `outbox_events` в той же
транзакции, где меняет бизнес-данные. После успешного commit отдельная команда
`outbox:relay` берёт pending-события и кладёт соответствующие Job в очередь.

Так приложение не теряет внешнее действие, если процесс упал между записью в БД
и отправкой в очередь.

## Когда использовать

Используйте Outbox для действий, которые нельзя безопасно выполнять прямо внутри
Handler-а:

- уведомления;
- письма;
- публикация событий наружу;
- синхронизация с внешними сервисами;
- любые побочные действия, которые должны случиться после успешной записи в БД.

Не используйте Outbox для обычной внутренней бизнес-логики, которая должна быть
выполнена сразу и находится внутри той же транзакции.

## Как работает поток

```text
1. Handler меняет бизнес-данные.
2. Handler вызывает OutboxEventStoreContract::add().
3. Handler вызывает EntityManagerInterface::run().
4. Транзакция успешно завершается.
5. outbox:relay берёт pending-события из outbox_events.
6. Relay находит Job для класса сообщения.
7. Relay кладёт Job в очередь.
8. Queue worker выполняет Job.
9. OutboxQueueStatusInterceptor меняет статус события на handled или failed.
```

Если отправка в очередь не удалась, событие остаётся доступным для повтора. Если
Job упала, interceptor записывает ошибку и переводит событие в failed или
оставляет queued для retry, если Job бросила `RetryException`.

## Основные файлы

```text
app/config/outbox.php
app/config/queue.php
app/database/migrations/20260525.153700_0_create_outbox_events_table.php
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php
app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php
app/src/Modules/Outbox/Application/Message/OutboxMessage.php
app/src/Modules/Outbox/Infrastructure/Spiral/Bootloader/OutboxBootloader.php
app/src/Modules/Outbox/Infrastructure/Persistence/Cycle/OutboxMessageLoader.php
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php
app/src/Modules/Outbox/Infrastructure/Spiral/Queue/OutboxQueueStatusInterceptor.php
app/src/Modules/Outbox/Infrastructure/Spiral/Console/OutboxRelayCommand.php
```

## Подключение модуля

Модуль подключается в `App\Shared\Infrastructure\Spiral\Kernel`.

В списке bootloader-ов должны быть:

```php
use App\Modules\Outbox\Infrastructure\Spiral\Bootloader\OutboxBootloader;

// ...

OutboxBootloader::class,
```

`OutboxBootloader` регистрирует сервисы модуля:

- `OutboxEventStoreContract`;
- `OutboxMessageLoaderContract`;
- `OutboxMessageSerializerContract`;
- `OutboxRelayContract`;
- `OutboxRelayWorkerContract`;
- `OutboxRelayLoopControlContract`;
- `OutboxJobRegistryContract`.

Он же регистрирует консольную команду `outbox:relay`.

## Настройка базы данных

Модуль хранит события в таблице `outbox_events`.

Миграция:

```text
app/database/migrations/20260525.153700_0_create_outbox_events_table.php
```

Перед использованием на новом окружении нужно применить миграции обычной
проектной командой.

## Настройка env

Основная настройка модуля:

```dotenv
OUTBOX_MAX_ATTEMPTS=100
```

`OUTBOX_MAX_ATTEMPTS` задаёт максимальное число попыток для публикации и
обработки outbox-события. Значение меньше `1` автоматически приводится к `1`.

Для очереди в обычном dev/runtime режиме используется RabbitMQ:

```dotenv
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=yoga_loka
RABBITMQ_PASSWORD=yoga_loka_password
RABBITMQ_VHOST=/
RABBITMQ_URL_VHOST=/
RABBITMQ_QUEUE_NAME=yoga_loka_jobs
RABBITMQ_QUEUE_PREFETCH=100
RABBITMQ_QUEUE_DURABLE=true
RABBITMQ_EXCHANGE_NAME=yoga_loka_jobs
RABBITMQ_EXCHANGE_TYPE=direct
RABBITMQ_EXCHANGE_DURABLE=true
RABBITMQ_ROUTING_KEY=yoga_loka_jobs
RABBITMQ_REQUEUE_ON_FAIL=false
```

`RABBITMQ_VHOST` задаёт имя vhost в RabbitMQ. `RABBITMQ_URL_VHOST` задаёт path-часть
AMQP-адреса RoadRunner и должна начинаться со слэша. Для нестандартного vhost
используй пару значений:

```dotenv
RABBITMQ_VHOST=staging
RABBITMQ_URL_VHOST=/staging
```

Так RabbitMQ создаёт vhost `staging`, а RoadRunner получает корректный адрес
`amqp://...:5672/staging`.

В тестах используется `QUEUE_CONNECTION=sync`, чтобы тесты не зависели от
RabbitMQ, если конкретный тест не проверяет RabbitMQ явно.

## Настройка очереди

Для каждого outbox Job нужно добавить две записи в `app/config/queue.php`.

В `registry.handlers`:

```php
OutboxDebugLogJob::class => OutboxDebugLogJob::class,
```

В `registry.serializers`:

```php
OutboxDebugLogJob::class => OutboxQueueSerializer::class,
```

`OutboxQueueSerializer` нужен, потому что в очередь отправляется не полное
сообщение, а короткий envelope:

```text
outboxId
outboxType
```

Сам payload сообщения хранится в таблице `outbox_events`.

В `interceptors.consume` должен быть `OutboxQueueStatusInterceptor`:

```php
'consume' => [
    ErrorHandlerInterceptor::class,
    OutboxQueueStatusInterceptor::class,
    RetryPolicyInterceptor::class,
],
```

Interceptor обновляет статус outbox-события после выполнения Job.

Порядок interceptor-ов критичен для корректности классификации ошибок и не должен
меняться. `RetryPolicyInterceptor` обязан стоять ниже (внутри)
`OutboxQueueStatusInterceptor`: только тогда исключение Job уже преобразовано в
`RetryException`, и статус-interceptor отличает «оставить на повтор» от
«окончательно failed». Если переставить эти два interceptor-а местами или убрать
политику ретраев, классификация молча инвертируется — повтор запишется как
окончательный провал или наоборот. Перестановку не ловит ни один тест,
проверяющий outbox в изоляции, поэтому при правке `app/config/queue.php` порядок
менять нельзя.

## Как добавить новое outbox-сообщение

### 1. Создать класс сообщения

Сообщение должно реализовать `OutboxMessage`.

Пример:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Message;

use App\Modules\Outbox\Application\Message\OutboxMessage;

final readonly class SendWelcomeEmailMessage implements OutboxMessage
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}
```

Класс сообщения должен быть простым DTO. Не кладите в него сервисы, Entity и
объекты инфраструктуры. Сериализацию payload делает инфраструктурный
`OutboxMessageSerializerContract`, поэтому сообщение не описывает
`jsonSerialize()` вручную.

### 2. Создать Job

Job получает технический `OutboxQueueEnvelope`, загружает настоящее сообщение из
`outbox_events` через `OutboxMessageLoaderContract` и вызывает обычную
Application-команду с бизнес-данными.

В очереди лежит только короткий payload:

```text
outboxId
outboxType
```

Настоящий payload хранится в БД:

```text
SendWelcomeEmailMessage
  userId
  email
```

Минимальный пример:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Spiral\Job;

use App\Modules\Notification\Application\Command\SendWelcomeEmail\SendWelcomeEmailCommand;
use App\Modules\Notification\Application\Command\SendWelcomeEmail\SendWelcomeEmailHandler;
use App\Modules\Notification\Application\Message\SendWelcomeEmailMessage;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\JobHandler;

final class SendWelcomeEmailJob extends JobHandler
{
    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        SendWelcomeEmailHandler $sendWelcomeEmailHandler,
    ): void {
        $sendWelcomeEmailMessage = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: SendWelcomeEmailMessage::class,
        );

        $commandBus->dispatch(
            command: new SendWelcomeEmailCommand(
                userId: $sendWelcomeEmailMessage->userId,
                email: $sendWelcomeEmailMessage->email,
            ),
            handler: $sendWelcomeEmailHandler->handle(...),
        );
    }
}
```

Job не должна сама менять статус outbox-события. Это делает
`OutboxQueueStatusInterceptor`.

`outboxId` остаётся техническим ключом. Если внешний сервис поддерживает
idempotency key, Job или технический адаптер может использовать
`$payload->outboxEventId->value()`. Но обычная бизнес-команда не должна получать
`outboxId`, если у неё нет отдельной бизнес-причины знать про Outbox.

### 3. Зарегистрировать сообщение и Job в OutboxJobRegistryContract

Регистрация делается в bootloader-е.

Для встроенного debug-сообщения это выглядит так:

```php
$outboxJobRegistry->register(
    outboxMessageClass: OutboxDebugLogMessage::class,
    outboxJobClass: OutboxDebugLogJob::class,
);
```

Если сообщение относится к другому модулю, лучше добавить bootloader этого
модуля и зарегистрировать пару там, чтобы Outbox не зависел от чужой предметной
логики.

Пример:

```php
final class NotificationOutboxBootloader extends Bootloader
{
    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: SendWelcomeEmailMessage::class,
            outboxJobClass: SendWelcomeEmailJob::class,
        );
    }
}
```

Этот bootloader нужно добавить в `Kernel`.

### 4. Зарегистрировать Job в queue.php

Добавьте Job в `registry.handlers`:

```php
SendWelcomeEmailJob::class => SendWelcomeEmailJob::class,
```

Добавьте сериализатор в `registry.serializers`:

```php
SendWelcomeEmailJob::class => OutboxQueueSerializer::class,
```

### 5. Сохранить событие в Handler-е

В бизнес Handler-е добавьте `OutboxEventStoreContract` через constructor
injection и сохраните событие до `EntityManagerInterface::run()`.

Пример:

```php
final readonly class RegisterUserHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxEventStoreContract $outboxEventStore,
    ) {}

    #[Transactional]
    public function handle(RegisterUserCommand $command): void
    {
        $user = User::create(/* ... */);

        $this->entityManager->persist($user);
        $this->outboxEventStore->add(new SendWelcomeEmailMessage(
            userId: $user->id->value(),
            email: $user->email->value(),
        ));

        $this->entityManager->run();
    }
}
```

Важно: событие должно сохраняться в той же транзакции, что и бизнес-изменение.
Для этого Handler должен использовать `#[Transactional]`, если сценарий должен
быть атомарным.

## Как запустить relay

Один раз обработать пачку:

```bash
php app.php outbox:relay 100
```

Постоянный режим:

```bash
php app.php outbox:relay 100 --loop --sleep=1
```

Параметры:

- `limit` - размер одной пачки, по умолчанию `100`;
- `--loop` - запускать постоянно;
- `--sleep` - пауза между пустыми пачками, по умолчанию `1` секунда.

В Docker команды запускаются внутри контейнера приложения.

Пример:

```bash
docker compose -f docker/docker-compose.dev.yml exec app-http php app.php outbox:relay 100
```

## Важное ограничение relay

Постоянный relay нужно запускать только в одном экземпляре.

Сейчас выборка использует `FOR UPDATE` без `SKIP LOCKED`, потому что в проекте
запрещён ручной SQL, а Cycle ORM Select не даёт отдельного API для `SKIP LOCKED`.
Если запустить несколько постоянных `outbox:relay --loop`, они могут мешать друг
другу.

До отдельного технического решения безопасный режим такой:

```text
1 экземпляр outbox:relay --loop на окружение
```

## Статусы событий

Событие проходит такие основные состояния:

```text
pending    - событие ждёт relay;
publishing - relay забрал событие и пытается поставить Job в очередь;
queued     - Job поставлена в очередь;
handled    - Job успешно выполнена;
failed     - публикация или обработка окончательно упала.
```

`handled` и `failed` считаются финальными статусами. Повторная доставка Job для
финального события будет пропущена interceptor-ом.

## Повторы и ошибки

Если relay не смог поставить Job в очередь:

- событие возвращается в `pending`;
- `attempts` увеличивается;
- `last_error` сохраняет текст ошибки;
- `available_at` сдвигается вперёд на внутреннюю паузу.

Если Job бросила `RetryException`:

- событие остаётся доступным для повтора;
- `attempts` увеличивается;
- `last_error` обновляется.

Если Job бросила другое исключение:

- событие переводится в `failed`;
- `last_error` обновляется.

Когда число попыток достигает `OUTBOX_MAX_ATTEMPTS`, событие переводится в
`failed`.

## Как читать сообщение внутри Job

Job читает сообщение через `OutboxMessageLoaderContract`. Application Handler
получает уже готовую команду с бизнес-данными и не читает `outbox_events`.

Пример есть в:

```text
app/src/Modules/Outbox/Infrastructure/Spiral/Job/OutboxDebugLogJob.php
```

Такой подход не дублирует большой payload в очереди, сохраняет источник правды в
БД и не протаскивает технический `outboxId` в бизнес-сценарии.

## Проверка после подключения нового сообщения

Минимальная проверка:

```bash
make test
make phpstan
```

После завершения глобальной задачи запускайте полный набор проверок:

```bash
make qa
```

Все эти команды должны запускаться через Docker, как указано в правилах проекта.

## Частые ошибки

Не зарегистрировали пару сообщение -> Job в `OutboxJobRegistryContract`.
Relay выбросит ошибку, потому что не знает, какую Job поставить в очередь.

Не добавили Job в `app/config/queue.php`.
RoadRunner Queue не найдёт обработчик задачи.

Не добавили `OutboxQueueSerializer` для Job.
Payload может быть сериализован не в том формате.

Вызвали внешний сервис напрямую из Handler-а.
Так можно потерять внешнее действие при сбое после commit или получить действие
без успешной записи бизнес-данных.

Сохранили outbox-событие вне бизнес-транзакции.
Так можно получить рассинхронизацию: бизнес-данные записались, а событие нет,
или наоборот.

Запустили несколько постоянных relay.
До поддержки безопасной параллельной выборки так делать нельзя.
