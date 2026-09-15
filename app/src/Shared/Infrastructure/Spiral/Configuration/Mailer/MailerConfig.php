<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Mailer;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

final readonly class MailerConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'mailer';
    }

    public function __construct(
        public string $dsn,
        public string $from,
        public string|null $queueConnection,
        public string|null $queue,
    ) {}
}
