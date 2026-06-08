<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Relay;

use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;

final readonly class SystemOutboxRelaySleeper implements OutboxRelaySleeperContract
{
    #[\Override]
    public function sleep(OutboxRelaySleepSeconds $seconds): void
    {
        // VO OutboxRelaySleepSeconds гарантирует минимум 1 секунду, поэтому отдельная
        // проверка «секунд больше нуля» не нужна — busy-spin исключён на уровне типа.
        \sleep($seconds->value());
    }
}
