<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Cache\RedisCacheStorage;
use Spiral\Cache\Storage\ArrayStorage;
use Spiral\Cache\Storage\FileStorage;

/**
 * Конфигурация компонента кэша.
 *
 * @link https://spiral.dev/docs/basics-cache
 */
return [
    /**
     * Хранилище кэша по умолчанию.
     */
    'default' => \env('CACHE_STORAGE', 'rr-local'),

    /**
     * Алиасы для предметных хранилищ.
     */
    'aliases' => [
        // 'user-data' => [
        //     'storage' => 'rr-local',
        //     'prefix' => 'user_'
        // ],
        // 'blog-data' => 'rr-local',
    ],

    /**
     * Хранилища кэша и их типы.
     */
    'storages' => [

        'rr-local' => [
            'type' => 'roadrunner',
            'driver' => 'local',
        ],

        'local' => [
            'type' => ArrayStorage::class,
        ],

        'file' => [
            'type' => FileStorage::class,
            'path' => \directory('runtime') . 'cache',
        ],

        'redis' => [
            'type' => RedisCacheStorage::class,
            'dsn' => \env('REDIS_DSN', 'redis://redis:6379/0'),
            'namespace' => \env('REDIS_CACHE_NAMESPACE', 'yoga_loka_cache'),
            'defaultLifetime' => (int) \env('REDIS_CACHE_DEFAULT_LIFETIME', 0),
        ],
    ],

    /**
     * Алиасы типов хранилищ.
     */
    'typeAliases' => [],
];
