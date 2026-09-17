<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Infrastructure\Storage\S3ClientProvider;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageServerConfig;
use Aws\S3\S3Client;

final readonly class FakeS3ClientProvider implements S3ClientProvider
{
    public function __construct(
        private S3Client $client,
    ) {}

    #[\Override]
    public function forServer(StorageServerConfig $serverConfig): S3Client
    {
        return $this->client;
    }
}
