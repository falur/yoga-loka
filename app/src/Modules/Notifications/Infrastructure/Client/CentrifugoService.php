<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Client;

use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Dto\RealtimeNotificationPayload;

/**
 * Реализация контракта realtime-доставки поверх CentrifugoClient.
 */
final readonly class CentrifugoService implements CentrifugoServiceContract
{
    public function __construct(
        private CentrifugoClient $centrifugoClient,
    ) {}

    #[\Override]
    public function publish(string $channel, RealtimeNotificationPayload $payload): void
    {
        $this->centrifugoClient->publish(channel: $channel, payload: $payload);
    }
}
