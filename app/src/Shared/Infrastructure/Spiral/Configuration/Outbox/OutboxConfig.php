<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Outbox;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

final readonly class OutboxConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'outbox';
    }

    public function __construct(
        public int $maxAttempts,
        public int $maxConsecutiveRelayFailures,
        public int $baseRelayRetryDelaySeconds,
        public int $maxRelayRetryDelaySeconds,
        // Срок аренды захваченного события: после него упавший relay-процесс отпускает
        // событие обратно в выборку (claim-lease). Отдельная настройка под окружения с
        // долгими push-операциями, где дефолтных 60 секунд может не хватить.
        public int $claimTimeoutSeconds,
        // Пауза перед повтором после неудачной публикации (publish-backoff). Доменно
        // отличается от claim-lease, поэтому это независимая настройка.
        public int $publishRetryDelaySeconds,
    ) {}
}
