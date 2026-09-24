<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use GianTiaga\SpiralOutbox\IntegrationEventContract;

/**
 * Записывающий дублёр хранилища событий outbox: запоминает, какие события записал сценарий.
 * В очередь хранилище ничего не ставит — доставки создаёт relay уже после commit-а.
 */
final class RecordingOutboxEventStore implements OutboxEventStoreContract
{
    /**
     * @var list<IntegrationEventContract>
     */
    public array $messages = [];

    #[\Override]
    public function add(IntegrationEventContract $event): string
    {
        $this->messages[] = $event;

        return 'outbox-test';
    }
}
