<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\Enum;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Queued = 'queued';
    case Handled = 'handled';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Handled, self::Failed => true,
            self::Pending, self::Publishing, self::Queued => false,
        };
    }
}
