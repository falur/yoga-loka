<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use Aws\Result;
use Aws\S3\S3Client;

/**
 * Управляемый дубль S3Client для проверки защитных веток S3MediaFileService, недостижимых
 * против реального MinIO (нештатные ответы S3 и проброс ошибок). Каждая операция S3 проходит
 * через __call и берёт поведение из переданного обработчика: он возвращает Aws\Result или
 * бросает исключение.
 */
final class FakeS3Client extends S3Client
{
    /**
     * @param array<string, callable(array<int|string, mixed>): Result> $handlers
     */
    public function __construct(
        private readonly array $handlers,
    ) {
        parent::__construct([
            // Имя сервиса задаём явно: иначе SDK выводит его из имени класса (FakeS3Client)
            // и не находит соответствующий сервис в манифесте.
            'service' => 's3',
            'exception_class' => \Aws\S3\Exception\S3Exception::class,
            'region' => 'us-east-1',
            'version' => 'latest',
            'endpoint' => 'http://minio:9000',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
    }

    /**
     * @param array<int, array<int|string, mixed>> $args
     */
    #[\Override]
    public function __call($name, array $args): Result
    {
        $handler = $this->handlers[$name] ?? null;

        if ($handler === null) {
            return new Result([]);
        }

        return $handler($args[0] ?? []);
    }
}
