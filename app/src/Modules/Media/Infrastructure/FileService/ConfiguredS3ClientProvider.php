<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Shared\Infrastructure\Configuration\Storage\StorageServerConfig;
use Aws\S3\S3Client;

final readonly class ConfiguredS3ClientProvider implements S3ClientProvider
{
    private const string DEFAULT_REGION = 'us-east-1';
    private const string DEFAULT_VERSION = 'latest';

    #[\Override]
    public function forServer(StorageServerConfig $serverConfig): S3Client
    {
        $options = [
            'version' => $serverConfig->version ?? self::DEFAULT_VERSION,
            'region' => $serverConfig->region ?? self::DEFAULT_REGION,
            'use_path_style_endpoint' => $serverConfig->options->usePathStyleEndpoint ?? true,
            'credentials' => [
                'key' => $serverConfig->key ?? '',
                'secret' => $serverConfig->secret ?? '',
                'token' => $serverConfig->token,
            ],
        ];

        if ($serverConfig->endpoint !== null) {
            $options['endpoint'] = $serverConfig->endpoint;
        }

        return new S3Client($options);
    }
}
