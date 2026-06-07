<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;

final readonly class InvalidOutboxPendingRow extends OutboxPendingRow
{
    public function __construct(
        public string $rawOutboxEventId,
        public OutboxLastError $lastError,
    ) {}
}
