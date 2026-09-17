<?php

declare(strict_types=1);

/**
 * Бакеты хранилища модуля Media: staging-загрузка, приватное и публичное хранение медиа.
 *
 * Значения дописываются в общую секцию Spiral 'storage' (позиция 'buckets') в MediaBootloader::init()
 * через ConfiguratorInterface::modify() — файл общего хранилища (app/config/storage.php) остаётся
 * владельцем только бакетов без владельца среди модулей.
 */
return [
    'media-upload' => [
        'server' => \env(key: 'MEDIA_UPLOAD_STORAGE_SERVER', default: 's3'),
        'bucket' => \env(key: 'MEDIA_UPLOAD_STORAGE_BUCKET', default: 'media-upload'),
        'prefix' => \env(key: 'MEDIA_UPLOAD_STORAGE_PREFIX', default: null),
        'visibility' => 'private',
    ],
    'media-private' => [
        'server' => \env(key: 'MEDIA_PRIVATE_STORAGE_SERVER', default: 's3'),
        'bucket' => \env(key: 'MEDIA_PRIVATE_STORAGE_BUCKET', default: 'media-private'),
        'prefix' => \env(key: 'MEDIA_PRIVATE_STORAGE_PREFIX', default: null),
        'visibility' => 'private',
    ],
    'media-public' => [
        'server' => \env(key: 'MEDIA_PUBLIC_STORAGE_SERVER', default: 's3'),
        'bucket' => \env(key: 'MEDIA_PUBLIC_STORAGE_BUCKET', default: 'media-public'),
        'prefix' => \env(key: 'MEDIA_PUBLIC_STORAGE_PREFIX', default: null),
        'visibility' => 'public',
    ],
];
