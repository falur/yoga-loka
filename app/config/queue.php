<?php

declare(strict_types=1);

use Spiral\Queue\Driver\SyncDriver;
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
    'default' => env('QUEUE_CONNECTION', 'in-memory'),

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
            // 'ping' => \App\Endpoint\Job\Ping::class
        ],

        /**
         * Соответствие имён задач и сериализаторов.
         * При постановке задачи используется указанный сериализатор, при обработке он же используется для десериализации.
         *
         * @link https://spiral.dev/docs/queue-jobs#changing-serializer
         */
        'serializers' => [
            // 'ping' => 'json',
            // \App\Endpoint\Job\Ping::class => 'json',
        ],
    ],

    /**
     * Interceptor позволяет подключиться к обработке задач до или после постановки и выполнения.
     *
     * @link https://spiral.dev/docs/queue-interceptors
     */
    'interceptors' => [
        // 'push' => [],
        // 'consume' => [],
    ],

    'driverAliases' => [
        'sync' => SyncDriver::class,
    ],
];
