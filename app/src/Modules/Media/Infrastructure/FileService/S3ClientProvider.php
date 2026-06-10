<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Shared\Infrastructure\Configuration\Storage\StorageServerConfig;
use Aws\S3\S3Client;

/**
 * Поставщик S3-клиента для сервера хранилища. Выделен отдельным seam-ом, чтобы построение
 * Aws\S3\S3Client можно было подменить в тестах (защитные ветки ответа S3 недостижимы против
 * реального MinIO).
 */
interface S3ClientProvider
{
    public function forServer(StorageServerConfig $serverConfig): S3Client;
}
