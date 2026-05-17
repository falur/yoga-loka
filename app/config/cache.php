<?php

declare(strict_types=1);

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
    'default' => env('CACHE_STORAGE', 'rr-local'),

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
            'path' => directory('runtime') . 'cache',
        ],
    ],

    /**
     * Алиасы типов хранилищ.
     */
    'typeAliases' => [],
];
