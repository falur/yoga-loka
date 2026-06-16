<?php

declare(strict_types=1);

use App\Modules\Media\Presentation\Job\ProcessMediaJob;
use App\Modules\Notifications\Presentation\Job\DispatchNotificationJob;
use App\Modules\Notifications\Presentation\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Presentation\Job\SendPushNotificationJob;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\Interceptor\Consume\ErrorHandlerInterceptor;
use Spiral\Queue\Interceptor\Consume\RetryPolicyInterceptor;
use Spiral\RoadRunner\Jobs\Queue\AMQP\ExchangeType;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\BeanstalkCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\SQSCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Queue;

/**
 * Конфигурация очередей.
 *
 * @link https://spiral.dev/docs/queue-configuration and https://spiral.dev/docs/queue-roadrunner
 */
return [
    /**
     * Подключение очереди по умолчанию.
     */
    'default' => \env('QUEUE_CONNECTION', 'in-memory'),

    /**
     * Алиасы подключений для предметных очередей.
     */
    'aliases' => [
        // 'mail-queue' => 'in-memory',
        // 'rating-queue' => 'sync',
    ],

    /**
     * Подключения очередей.
     * Драйверы: "sync", "roadrunner".
     *
     * @link https://spiral.dev/docs/queue-configuration
     */
    'connections' => [
        'sync' => [
            // Задача будет выполнена сразу, без постановки в очередь.
            'driver' => 'sync',
        ],
        'in-memory' => [
            'driver' => 'roadrunner',
            'pipeline' => 'memory',
        ],
        'rabbitmq' => [
            'driver' => 'roadrunner',
            'pipeline' => 'rabbitmq',
        ],
    ],

    /**
     * Динамические конвейеры для RoadRunner.
     *
     * @link https://spiral.dev/docs/queue-roadrunner#declaring-pipelines-in-configuration-file
     * Список доступных очередей: {@link https://roadrunner.dev/docs/queues-overview#creating-a-new-queue}
     */
    'pipelines' => [
        'memory' => [
            'connector' => new MemoryCreateInfo('local'),
            // Запускаем обработчик этого конвейера при старте.
            // Обработчик можно поставить на паузу консольной командой.
            // php app.php queue:pause local
            'consume' => true,
        ],
        'rabbitmq' => [
            'connector' => new AMQPCreateInfo(
                name: 'rabbitmq',
                prefetch: \max(1, (int) \env('RABBITMQ_QUEUE_PREFETCH', 100)),
                queue: (string) \env('RABBITMQ_QUEUE_NAME', 'yoga_loka_jobs'),
                exchange: (string) \env('RABBITMQ_EXCHANGE_NAME', 'yoga_loka_jobs'),
                exchangeType: ExchangeType::from((string) \env('RABBITMQ_EXCHANGE_TYPE', ExchangeType::Direct->value)),
                routingKey: (string) \env('RABBITMQ_ROUTING_KEY', 'yoga_loka_jobs'),
                requeueOnFail: (bool) \filter_var(
                    value: \env('RABBITMQ_REQUEUE_ON_FAIL', 'false'),
                    filter: FILTER_VALIDATE_BOOL,
                ),
                durable: (bool) \filter_var(
                    value: \env('RABBITMQ_QUEUE_DURABLE', 'true'),
                    filter: FILTER_VALIDATE_BOOL,
                ),
                exchangeDurable: (bool) \filter_var(
                    value: \env('RABBITMQ_EXCHANGE_DURABLE', 'true'),
                    filter: FILTER_VALIDATE_BOOL,
                ),
            ),
            'consume' => true,
        ],
        // 'amqp' => [
        //     'connector' => new AMQPCreateInfo('bus', ...),
        //     // Не запускаем обработчик этого конвейера при старте.
        //     // Обработчик можно запустить консольной командой.
        //     // php app.php queue:resume local
        //     'consume' => false
        // ],
        //
        // 'beanstalk' => [
        //     'connector' => new BeanstalkCreateInfo('bus', ...),
        // ],
        //
        // 'sqs' => [
        //     'connector' => new SQSCreateInfo('amazon', ...),
        // ],
    ],

    /**
     * Сериализатор для преобразования payload задачи в строку и обратно.
     *
     * @link https://spiral.dev/docs/queue-jobs/#job-payload-serialization
     */
    'defaultSerializer' => 'json',

    'registry' => [
        /**
         * Соответствие имён задач и обработчиков.
         * Когда обработчик очереди получает задачу, он ищет обработчик задачи здесь.
         *
         * (QueueInterface)->push('ping', ["url" => "http://site.com"]);
         *
         * @link https://spiral.dev/docs/queue-jobs#job-handler-registry
         */
        'handlers' => [
            // 'ping' => \App\Modules\System\Presentation\Job\Ping::class
            OutboxDebugLogJob::class => OutboxDebugLogJob::class,
            ProcessMediaJob::class => ProcessMediaJob::class,
            DispatchNotificationJob::class => DispatchNotificationJob::class,
            SendPushNotificationJob::class => SendPushNotificationJob::class,
            PublishRealtimeNotificationJob::class => PublishRealtimeNotificationJob::class,
        ],

        /**
         * Соответствие имён задач и сериализаторов.
         * При постановке задачи используется указанный сериализатор, при обработке он же используется для десериализации.
         *
         * @link https://spiral.dev/docs/queue-jobs#changing-serializer
         */
        'serializers' => [
            // 'ping' => 'json',
            // \App\Modules\System\Presentation\Job\Ping::class => 'json',
            OutboxDebugLogJob::class => OutboxQueueSerializer::class,
            ProcessMediaJob::class => OutboxQueueSerializer::class,
            DispatchNotificationJob::class => OutboxQueueSerializer::class,
            SendPushNotificationJob::class => OutboxQueueSerializer::class,
            PublishRealtimeNotificationJob::class => OutboxQueueSerializer::class,
        ],
    ],

    /**
     * Interceptor позволяет подключиться к обработке задач до или после постановки и выполнения.
     *
     * @link https://spiral.dev/docs/queue-interceptors
     */
    'interceptors' => [
        // 'push' => [],
        // Порядок критичен: RetryPolicyInterceptor обязан стоять ниже (внутри)
        // OutboxQueueStatusInterceptor, иначе исключение Job ещё не преобразовано в
        // RetryException и статус-interceptor спутает «оставить на повтор» с
        // «окончательно failed». Перестановка interceptor-ов местами или удаление
        // политики ретраев молча инвертирует классификацию ошибок outbox.
        'consume' => [
            ErrorHandlerInterceptor::class,
            OutboxQueueStatusInterceptor::class,
            RetryPolicyInterceptor::class,
        ],
    ],

    'driverAliases' => [
        'sync' => SyncDriver::class,
    ],
];
