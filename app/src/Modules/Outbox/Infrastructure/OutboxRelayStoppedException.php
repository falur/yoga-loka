<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure;

final class OutboxRelayStoppedException extends \DomainException
{
    public static function loopStoppedByExternalControl(): self
    {
        return new self('Outbox relay loop остановлен внешним контролем.');
    }
}
