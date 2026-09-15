<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Push;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

final readonly class PushConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'push';
    }

    public function __construct(
        public string $projectId,
        public string $credentialsFile,
    ) {}
}
