<?php

declare(strict_types=1);

use Cycle\Database\Config;

/**
 * Конфигурация подключений к базам данных.
 *
 * @link https://spiral.dev/docs/basics-orm#database
 */
return [
    /**
     * Логирование SQL-запросов через spiral/logger.
     *
     * @link https://spiral.dev/docs/basics-orm#logging
     */
    'logger' => [
        'default' => null,
        'drivers' => [
            // 'runtime' => 'stdout'
        ],
    ],

    /**
     * Подключение к базе данных по умолчанию.
     */
    'default' => 'default',

    /**
     * Список баз данных приложения.
     * Новая база добавляется в секцию "databases".
     */
    'databases' => [
        'default' => [
            'driver' => \env('DB_CONNECTION', 'sqlite'),
        ],
    ],

    /**
     * Настройки драйверов подключений.
     * Каждая база должна ссылаться на один из этих драйверов.
     */
    'drivers' => [
        'sqlite' => new Config\SQLiteDriverConfig(
            connection: new Config\SQLite\MemoryConnectionConfig(),
            queryCache: \env('DB_QUERY_CACHE', true),
            options: [
                'logQueryParameters' => \env('DB_LOG_QUERY_PARAMETERS', false),
                'logInterpolatedQueries' => \env('DB_LOG_INTERPOLATED_QUERIES', false),
                'withDatetimeMicroseconds' => \env('DB_WITH_DATETIME_MICROSECONDS', false),
            ],
        ),
        ...(\extension_loaded('pdo_pgsql') ? [
            'pgsql' => new Config\PostgresDriverConfig(
                connection: new Config\Postgres\TcpConnectionConfig(
                    database: \env('DB_DATABASE', 'spiral'),
                    host: \env('DB_HOST', '127.0.0.1'),
                    port: (int) \env('DB_PORT', 5432),
                    user: \env('DB_USERNAME', 'postgres'),
                    password: \env('DB_PASSWORD', ''),
                ),
                schema: \env('DB_SCHEMA', 'public'),
                queryCache: \env('DB_QUERY_CACHE', true),
                options: [
                    'logQueryParameters' => \env('DB_LOG_QUERY_PARAMETERS', false),
                    'logInterpolatedQueries' => \env('DB_LOG_INTERPOLATED_QUERIES', false),
                    'withDatetimeMicroseconds' => \env('DB_WITH_DATETIME_MICROSECONDS', false),
                ],
            ),
        ] : []),
        ...(\extension_loaded('pdo_mysql') ? [
            'mysql' => new Config\MySQLDriverConfig(
                connection: new Config\MySQL\TcpConnectionConfig(
                    database: \env('DB_DATABASE', 'spiral'),
                    host: \env('DB_HOST', '127.0.0.1'),
                    port: (int) \env('DB_PORT', 3307),
                    user: \env('DB_USERNAME', 'root'),
                    password: \env('DB_PASSWORD', ''),
                ),
                queryCache: \env('DB_QUERY_CACHE', true),
                options: [
                    'logQueryParameters' => \env('DB_LOG_QUERY_PARAMETERS', false),
                    'logInterpolatedQueries' => \env('DB_LOG_INTERPOLATED_QUERIES', false),
                    'withDatetimeMicroseconds' => \env('DB_WITH_DATETIME_MICROSECONDS', false),
                ],
            ),
        ] : []),
        // ...
    ],
];
