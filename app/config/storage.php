<?php

declare(strict_types=1);

use Spiral\Storage\Storage;

/**
 * Конфигурация файлового хранилища.
 *
 * @link https://spiral.dev/docs/advanced-storage
 */
return [
    /**
     * -------------------------------------------------------------------------
     *  Bucket по умолчанию
     * -------------------------------------------------------------------------
     */
    'default' => \env('STORAGE_DEFAULT', Storage::DEFAULT_STORAGE),

    /**
     * -------------------------------------------------------------------------
     *  Серверы хранилища
     * -------------------------------------------------------------------------
     */
    'servers' => [
        'local' => [
            //
            // Тип локального сервера.
            //
            'adapter' => 'local',

            //
            // Директория для хранения файлов.
            //
            'directory' => \directory('public') . 'uploads',

            //
            // Соответствие visibility и прав доступа для файлов и директорий.
            // Допустимые значения visibility: "private" и "public".
            //
            'visibility' => [
                'public' => ['file' => 0o644, 'dir' => 0o755],
                'private' => ['file' => 0o600, 'dir' => 0o700],

                'default' => 'public',
            ],
        ],

        's3' => [
            'adapter' => 's3',
            'region' => \env('S3_REGION', 'us-east-1'),
            'version' => \env('S3_VERSION', 'latest'),
            'bucket' => \env('S3_BUCKET', 'yoga-loka'),
            'key' => \env('S3_KEY', 'yoga_loka'),
            'secret' => \env('S3_SECRET', 'yoga_loka_password'),
            'token' => \env('S3_TOKEN', null),
            'expires' => \env('S3_EXPIRES', null),
            'visibility' => \env('S3_VISIBILITY', 'public'),
            'prefix' => '',
            'endpoint' => \env('S3_ENDPOINT', 'http://minio:9000'),
            'options' => [
                'use_path_style_endpoint' => true,
            ],
        ],
    ],

    /**
     * -------------------------------------------------------------------------
     *  Bucket-ы хранилища
     * -------------------------------------------------------------------------
     */
    'buckets' => [
        'default' => [
            'server' => 'local',
        ],
        's3' => [
            'server' => 's3',
            'bucket' => \env('S3_BUCKET', 'yoga-loka'),
        ],
        's3-test' => [
            'server' => 's3',
            'bucket' => \env('S3_TEST_BUCKET', 'yoga-loka-test'),
        ],
        'media-upload' => [
            'server' => \env('MEDIA_UPLOAD_STORAGE_SERVER', 's3'),
            'bucket' => \env('MEDIA_UPLOAD_STORAGE_BUCKET', 'media-upload'),
            'prefix' => \env('MEDIA_UPLOAD_STORAGE_PREFIX', null),
            'visibility' => 'private',
        ],
        'media-private' => [
            'server' => \env('MEDIA_PRIVATE_STORAGE_SERVER', 's3'),
            'bucket' => \env('MEDIA_PRIVATE_STORAGE_BUCKET', 'media-private'),
            'prefix' => \env('MEDIA_PRIVATE_STORAGE_PREFIX', null),
            'visibility' => 'private',
        ],
        'media-public' => [
            'server' => \env('MEDIA_PUBLIC_STORAGE_SERVER', 's3'),
            'bucket' => \env('MEDIA_PUBLIC_STORAGE_BUCKET', 'media-public'),
            'prefix' => \env('MEDIA_PUBLIC_STORAGE_PREFIX', null),
            'visibility' => 'public',
        ],
    ],
];
