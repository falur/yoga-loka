<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use Spiral\Queue\HandlerInterface;

final readonly class FailingOutboxRelayJob implements HandlerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function handle(string $name, string $id, array $payload): void
    {
        throw new \RuntimeException('Sync Job упал.');
    }
}
