<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\HandlerInterface;

final readonly class RetryingOutboxRelayJob implements HandlerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function handle(string $name, string $id, array $payload): void
    {
        throw new RetryException(reason: 'Sync Job просит повтор.');
    }
}
