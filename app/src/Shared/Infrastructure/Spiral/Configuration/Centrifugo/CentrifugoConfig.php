<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Centrifugo;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

final readonly class CentrifugoConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'centrifugo';
    }

    public function __construct(
        public string $apiUrl,
        public string $apiKey,
    ) {}
}
