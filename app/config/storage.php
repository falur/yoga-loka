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
    'default' => env('STORAGE_DEFAULT', Storage::DEFAULT_STORAGE),

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
            'directory' => directory('public') . 'uploads',

            //
            // Соответствие visibility и прав доступа для файлов и директорий.
            // Допустимые значения visibility: "private" и "public".
            //
            'visibility' => [
                'public' => ['file' => 0644, 'dir' => 0755],
                'private' => ['file' => 0600, 'dir' => 0700],

                'default' => 'public',
            ],
        ],

        // Пример конфигурации S3.
        /*'s3' => [
            //
            // Тип S3-сервера: "s3" или "s3-async".
            //
            'adapter' => 's3',

            //
            // Регион S3, например "eu-north-1".
            //  - https://s3.console.aws.amazon.com/s3/home
            //
            'region' => env('S3_REGION'),

            //
            // Версия S3 API.
            //
            'version' => env('S3_VERSION', 'latest'),

            //
            // Имя bucket в S3.
            //  - https://s3.console.aws.amazon.com/s3/home
            //
            'bucket' => env('S3_BUCKET'),

            //
            // Ключ доступа S3.
            //  - https://console.aws.amazon.com/iam/home#/security_credentials
            //
            'key' => env('S3_KEY'),

            //
            // Секретный ключ S3 или путь к файлу с ключом.
            //  - https://console.aws.amazon.com/iam/home#/security_credentials
            //
            'secret' => env('S3_SECRET'),

            //
            // Токен S3 credentials.
            //
            'token' => env('S3_TOKEN', null),

            //
            // Время истечения S3 credentials.
            //
            'expires' => env('S3_EXPIRES', null),

            //
            // Visibility файлов S3.
            //
            'visibility' => env('S3_VISIBILITY', 'public'),

            //
            // Префикс директории для S3 bucket.
            //
            'prefix' => '',

            //
            // Endpoint S3 API для серверов, отличных от Amazon.
            //
            'endpoint' => env('S3_ENDPOINT', null),

            //
            // Дополнительные опции S3.
            // Например, "use_path_style_endpoint" нужен для MinIO.
            // См. https://github.com/spiral/framework/issues/416
            //
            'options' => [
                'use_path_style_endpoint' => true,
            ]
        ], */
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
        /*
        'images' => [
            'server' => 's3',
        ],*/
    ],
];
