<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Записывающий outbox-стор для проверки, какие сообщения поставлены в очередь.
 */
final class RecordingOutboxEventStore implements IntegrationEventStoreContract
{
    /**
     * @var list<IntegrationEvent>
     */
    public array $messages = [];

    #[\Override]
    public function add(IntegrationEvent $integrationEvent): string
    {
        $this->messages[] = $integrationEvent;

        return 'outbox-test';
    }
}
