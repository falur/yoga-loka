<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

final readonly class SerializedOutboxMessage
{
    public function __construct(
        public string $type,
        public string $payload,
    ) {}
}
