<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Contract;

use App\Modules\Outbox\Application\Message\OutboxMessage;

interface OutboxJobRegistryContract
{
    /**
     * @param class-string<OutboxMessage> $outboxMessageClass
     * @param class-string $outboxJobClass
     */
    public function register(string $outboxMessageClass, string $outboxJobClass): void;

    /**
     * @return class-string
     */
    public function jobFor(OutboxMessage $outboxMessage): string;
}
