<?php

declare(strict_types=1);

/**
 * Конфигурация почты.
 *
 * @link https://spiral.dev/docs/advanced-sendit
 */
return [
    /**
     * DSN транспорта, который используется для отправки писем.
     * @see https://symfony.com/doc/current/mailer.html#using-built-in-transports
     */
    'dsn' => env('MAILER_DSN', 'smtp://user:pass@mailhog:25'),

    /**
     * Глобальный адрес отправителя.
     */
    'from' => env('MAILER_FROM', 'Spiral <sendit@local.host>'),

    /**
     * Подключение и очередь для отправки писем через queue.
     */
    'queueConnection' => env('MAILER_QUEUE_CONNECTION'),
    'queue' => env('MAILER_QUEUE', 'local'),
];
