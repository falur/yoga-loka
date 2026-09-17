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
     *
     * Каждая миграция физически переехала в свой модуль (`Infrastructure/Persistence/Cycle/Migration`)
     * и регистрируется его bootloader-ом через `vendorDirectories`. Этот путь остаётся заглушкой без
     * единого файла: `Cycle\Migrations\FileRepository` при отсутствии каталога просто не находит файлов.
     */
    'directory' => \directory('runtime') . 'migrations/',

    /**
     * Таблица для хранения статуса миграций по базам данных.
     */
    'table' => 'migrations',

    /**
     * Если true, миграции запускаются без интерактивного подтверждения.
     */
    'safe' => \env('SAFE_MIGRATIONS', \spiral(AppEnvironment::class)->isProduction()),
];
