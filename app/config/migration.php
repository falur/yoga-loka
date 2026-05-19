<?php

declare(strict_types=1);

use Spiral\Boot\Environment\AppEnvironment;

/**
 * Конфигурация миграций.
 *
 * @link https://spiral.dev/docs/basics-orm#migrations
 */
return [
    /**
     * Директория для файлов миграций.
     */
    'directory' => \directory('app') . 'database/migrations/',

    /**
     * Таблица для хранения статуса миграций по базам данных.
     */
    'table' => 'migrations',

    /**
     * Если true, миграции запускаются без интерактивного подтверждения.
     */
    'safe' => \env('SAFE_MIGRATIONS', \spiral(AppEnvironment::class)->isProduction()),
];
