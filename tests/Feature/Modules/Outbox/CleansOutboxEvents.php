<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox;

use Cycle\Database\DatabaseInterface;

/**
 * @mixin \Tests\TestCase
 */
trait CleansOutboxEvents
{
    protected function cleanOutboxEvents(): void
    {
        $this->getContainer()->get(DatabaseInterface::class)->delete('outbox_events')->run();
    }
}
