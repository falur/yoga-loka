<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\Interceptor\Consume\ErrorHandlerInterceptor;
use Spiral\RoadRunner\Jobs\Queue\AMQP\ExchangeType;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\BeanstalkCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\SQSCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Queue;

/**
 * Общие параметры очередей RabbitMQ. Имена очереди, exchange и routing key собираются из одного
 * префикса окружения и имени очереди назначения, остальные параметры у всех очередей одинаковы.
 */
$rabbitMqQueuePrefix = (string) \env('RABBITMQ_QUEUE_PREFIX', 'yoga_loka');
$rabbitMqPrefetch = \max(1, (int) \env('RABBITMQ_QUEUE_PREFETCH', 100));
$rabbitMqExchangeType = ExchangeType::from((string) \env('RABBITMQ_EXCHANGE_TYPE', ExchangeType::Direct->value));
$rabbitMqRequeueOnFail = (bool) \filter_var(
    value: \env('RABBITMQ_REQUEUE_ON_FAIL', 'false'),
    filter: FILTER_VALIDATE_BOOL,
);
$rabbitMqQueueDurable = (bool) \filter_var(
    value: \env('RABBITMQ_QUEUE_DURABLE', 'true'),
    filter: FILTER_VALIDATE_BOOL,
);
$rabbitMqExchangeDurable = (bool) \filter_var(
    value: \env('RABBITMQ_EXCHANGE_DURABLE', 'true'),
    filter: FILTER_VALIDATE_BOOL,
);

/**
 * Имя ресурса RabbitMQ для очереди назначения: очередь, exchange и routing key называются одинаково.
 */
$rabbitMqResourceName = static fn(QueueName $queueName): string => $rabbitMqQueuePrefix . '_' . $queueName->value;

/**
 * Конвейер очереди назначения: у каждой очереди свой коннектор AMQP и свой обработчик.
 *
 * @return array{connector: AMQPCreateInfo, consume: bool}
 */
$rabbitMqPipeline = static fn(QueueName $queueName): array => [
    'connector' => new AMQPCreateInfo(
        name: $queueName->value,
        prefetch: $rabbitMqPrefetch,
        queue: $rabbitMqResourceName($queueName),
        exchange: $rabbitMqResourceName($queueName),
        exchangeType: $rabbitMqExchangeType,
        routingKey: $rabbitMqResourceName($queueName),
        requeueOnFail: $rabbitMqRequeueOnFail,
        durable: $rabbitMqQueueDurable,
        exchangeDurable: $rabbitMqExchangeDurable,
    ),
    'consume' => true,
];

/**
 * Конфигурация очередей.
 *
 * @link https://spiral.dev/docs/queue-configuration and https://spiral.dev/docs/queue-roadrunner
 */
return [
    /**
     * Подключение очереди по умолчанию. Очередь доставки задаёт маршрут outbox явно
     * (`Options::onQueue()`), поэтому это подключение остаётся запасным: оно определяет драйвер
     * отправки и pipeline только для отправки без объявленной очереди.
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
        QueueName::Mail->value => [
            'driver' => 'roadrunner',
            'pipeline' => QueueName::Mail->value,
        ],
        QueueName::Media->value => [
            'driver' => 'roadrunner',
            'pipeline' => QueueName::Media->value,
        ],
        QueueName::Notifications->value => [
            'driver' => 'roadrunner',
            'pipeline' => QueueName::Notifications->value,
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
        QueueName::Mail->value => $rabbitMqPipeline(QueueName::Mail),
        QueueName::Media->value => $rabbitMqPipeline(QueueName::Media),
        QueueName::Notifications->value => $rabbitMqPipeline(QueueName::Notifications),
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
         * Job-потребители outbox сюда не перечисляются: типом задачи служит полное имя класса Job,
         * который relay берёт из маршрута секции `outbox`, а обработчик Spiral находит по этому
         * же имени класса.
         *
         * @link https://spiral.dev/docs/queue-jobs#job-handler-registry
         */
        'handlers' => [
            // 'ping' => \App\Modules\System\Infrastructure\Spiral\Job\Ping::class
        ],

        /**
         * Соответствие имён задач и сериализаторов.
         * При постановке задачи используется указанный сериализатор, при обработке он же используется для десериализации.
         *
         * Job-потребители outbox сюда не перечисляются — см. комментарий у 'handlers'.
         *
         * @link https://spiral.dev/docs/queue-jobs#changing-serializer
         */
        'serializers' => [
            // 'ping' => 'json',
            // \App\Modules\System\Infrastructure\Spiral\Job\Ping::class => 'json',
        ],
    ],

    /**
     * Interceptor позволяет подключиться к обработке задач до или после постановки и выполнения.
     *
     * @link https://spiral.dev/docs/queue-interceptors
     */
    'interceptors' => [
        // 'push' => [],
        // Политики повторов Spiral здесь нет: повторами владеет только outbox — их число задаёт
        // список пауз маршрута, а физического возврата задачи в RabbitMQ не происходит
        // (`requeue_on_fail: false`). Интерсептор доставки дописывает сюда bootloader пакета
        // gian-tiaga/spiral-outbox: он закрывает доставку и сам решает, повторить её или нет.
        'consume' => [
            ErrorHandlerInterceptor::class,
        ],
    ],

    'driverAliases' => [
        'sync' => SyncDriver::class,
    ],
];
