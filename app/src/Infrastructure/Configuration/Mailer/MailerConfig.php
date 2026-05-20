<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Mailer;

use App\Infrastructure\Configuration\TypedConfig;

final readonly class MailerConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'mailer';
    }

    public function __construct(
        public string $dsn,
        public string $from,
        public ?string $queueConnection,
        public ?string $queue,
    ) {}
}
