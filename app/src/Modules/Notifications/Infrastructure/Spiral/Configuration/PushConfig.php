<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Configuration;

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
