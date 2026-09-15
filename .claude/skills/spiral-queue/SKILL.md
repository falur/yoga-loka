---
name: spiral-queue
description: >-
  Справочник по Queue (очереди и задачи) в Spiral Framework.
  Используй при создании Job-обработчиков, отправке задач в очередь,
  настройке сериализации, retry-политик и обработке ошибок.
user-invocable: false
---

# Spiral Framework: Queue (очереди и задачи)

## Создание Job Handler

```php
namespace App\Job;

use Spiral\Queue\JobHandler;

final class SendEmailVerificationCodeJob extends JobHandler
{
    public function invoke(
        string $id,
        array $payload,
        MailerInterface $mailer,
    ): void {
        $mailer->send(...);
    }
}
```

> Метод `invoke` всегда возвращает `void`. Зависимости инжектируются через параметры.

## Отправка задач

### В очередь по умолчанию

```php
use Spiral\Queue\QueueInterface;

public function handle(QueueInterface $queue): void
{
    $queue->push(SendEmailVerificationCodeJob::class, [
        'email' => $command->email,
        'code' => $code,
    ]);
}
```

### В конкретную очередь

```php
use Spiral\Queue\QueueConnectionProviderInterface;

public function __construct(
    private readonly QueueConnectionProviderInterface $provider,
) {}

public function handle(): void
{
    $this->provider->getConnection('high_priority')
        ->push(SampleJob::class, ['value' => 123]);
}
```

### Payload — массив, объект или строка

```php
// Массив
$queue->push(SampleJob::class, ['value' => 123]);

// Объект
$queue->push(SampleJob::class, new UserPayload(id: 123));

// Строка
$queue->push(SampleJob::class, 'some string');
```

## Опции задач

### Задержка

```php
use Spiral\Queue\Options;

$queue->push(
    SampleJob::class,
    ['value' => 123],
    (new Options())->withDelay(3600),  // через 1 час
);
```

### Заголовки

```php
$queue->push(
    SampleJob::class,
    ['value' => 123],
    (new Options())->withHeader('user_id', '123'),
);
```

### Целевая очередь

```php
$queue->push(
    SampleJob::class,
    ['value' => 123],
    (new Options())->withQueue('high_priority'),
);
```

## Сериализация Payload

### Конфигурация по умолчанию

```php
// app/config/queue.php
return [
    'defaultSerializer' => 'json',
];
```

### Сериализатор для конкретной задачи

```php
use Spiral\Queue\Attribute\Serializer;

#[Serializer('json')]
final class PingJob extends JobHandler
{
    public function invoke(array $payload): void {}
}
```

## Реестр обработчиков

### Через атрибут

```php
use Spiral\Queue\Attribute\JobHandler as Handler;

#[Handler('sample::job')]
final class SampleJob extends \Spiral\Queue\JobHandler
{
    public function invoke(array $payload): void {}
}
```

### Через QueueRegistry в bootloader

```php
public function boot(QueueRegistry $registry): void
{
    $registry->setHandler('sample::job', SampleJob::class);
}
```

## Retry Policy (повтор при ошибке)

### Конфигурация interceptor

```php
// app/config/queue.php
return [
    'interceptors' => [
        'consume' => [
            \Spiral\Queue\Interceptor\Consume\RetryPolicyInterceptor::class,
        ],
    ],
];
```

### Через атрибут

```php
use Spiral\Queue\Attribute\RetryPolicy;

#[RetryPolicy(maxAttempts: 3, delay: 5, multiplier: 2)]
final class ImportDataJob extends JobHandler
{
    public function invoke(array $payload): void
    {
        // При ошибке: 1я попытка через 5с, 2я через 10с, 3я через 20с
    }
}
```

### Через RetryableException

```php
use Spiral\Queue\Exception\RetryableExceptionInterface;
use Spiral\Queue\RetryPolicy;

class RetryableException extends \Exception implements RetryableExceptionInterface
{
    public function isRetryable(): bool
    {
        return true;
    }

    public function getRetryPolicy(): ?RetryPolicyInterface
    {
        return new RetryPolicy(maxAttempts: 3, delay: 5, multiplier: 2);
    }
}
```

## Обработка неуспешных задач

```php
use Spiral\Queue\Failed\FailedJobHandlerInterface;

class DatabaseFailedJobsHandler implements FailedJobHandlerInterface
{
    public function handle(
        string $driver,
        string $queue,
        string $job,
        array $payload,
        \Throwable $e,
    ): void {
        $this->database->insert('failed_jobs')->values([
            'driver' => $driver,
            'queue' => $queue,
            'job_name' => $job,
            'payload' => $this->serializer->serialize($payload),
            'error' => $e->getMessage(),
        ])->run();
    }
}
```

Регистрация:
```php
protected const SINGLETONS = [
    FailedJobHandlerInterface::class => DatabaseFailedJobsHandler::class,
];
```

## События

| Событие | Описание |
|---------|---------|
| `JobProcessing` | ДО выполнения обработчика |
| `JobProcessed` | ПОСЛЕ выполнения обработчика |

## Ключевые правила

1. Задачи пушатся по FQCN класса: `SendEmailVerificationCodeJob::class`
2. Промежуточные enum-ы для имён задач — лишняя прослойка
3. `invoke()` всегда `void`
4. Зависимости инжектируются через параметры `invoke()`
5. Для сериализации payload — JSON по умолчанию
