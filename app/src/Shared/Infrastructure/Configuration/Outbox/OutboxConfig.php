<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Outbox;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class OutboxConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'outbox';
    }

    public function __construct(
        public int $maxAttempts,
    ) {}
}
