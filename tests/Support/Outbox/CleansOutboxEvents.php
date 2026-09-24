<?php

declare(strict_types=1);

namespace Tests\Support\Outbox;

use Cycle\Database\DatabaseInterface;
use GianTiaga\SpiralOutbox\Config\OutboxConfig;

/**
 * Очистка обеих таблиц обмена: доставки ссылаются на события, поэтому сначала уходят они.
 * Имена таблиц берутся из настройки пакета, а не из литералов.
 *
 * @mixin \Tests\TestCase
 */
trait CleansOutboxEvents
{
    protected function cleanOutboxEvents(): void
    {
        $database = $this->getContainer()->get(DatabaseInterface::class);
        $outboxConfig = $this->getContainer()->get(OutboxConfig::class);

        $database->delete($outboxConfig->deliveriesTableName)->run();
        $database->delete($outboxConfig->eventsTableName)->run();
    }
}
