<?php

declare(strict_types=1);

namespace Tests\Support\Outbox;

use Cycle\Database\DatabaseInterface;
use GianTiaga\SpiralOutbox\Config\OutboxConfig;
use GianTiaga\SpiralOutbox\Database\OutboxDeliveryColumns;
use GianTiaga\SpiralOutbox\Database\OutboxEventColumns;
use GianTiaga\SpiralOutbox\OutboxEventRouterContract;
use GianTiaga\SpiralOutbox\Relay\OutboxRelayPassContract;

/**
 * Проход relay и чтение таблиц обмена из теста.
 *
 * В тестах подключение очереди — `sync`, поэтому полный проход выполняет Job в этом же процессе и
 * доставка успевает получить свой конечный статус до возврата из `runOutboxRelayPass()`. Тесту,
 * который вызывает Job сам, нужен только первый этап — его даёт `routeOutboxEvents()`.
 *
 * @mixin \Tests\TestCase
 */
trait RunsOutboxRelay
{
    protected function runOutboxRelayPass(): int
    {
        return $this->getContainer()->get(OutboxRelayPassContract::class)->run();
    }

    protected function routeOutboxEvents(): int
    {
        return $this->getContainer()->get(OutboxEventRouterContract::class)->route();
    }

    /**
     * @return list<OutboxDeliveryRow>
     */
    protected function outboxDeliveries(): array
    {
        $rows = $this->getContainer()->get(DatabaseInterface::class)
            ->select()
            ->from($this->getContainer()->get(OutboxConfig::class)->deliveriesTableName)
            ->orderBy(OutboxDeliveryColumns::CREATED_AT)
            ->fetchAll();

        return \array_values(\array_map(
            callback: OutboxDeliveryRow::fromDatabaseRow(...),
            array: $rows,
        ));
    }

    /**
     * @param class-string $jobClass
     */
    protected function outboxDeliveryOf(string $jobClass): OutboxDeliveryRow
    {
        foreach ($this->outboxDeliveries() as $delivery) {
            if ($delivery->jobClass === $jobClass) {
                return $delivery;
            }
        }

        self::fail(\sprintf('Доставки для Job %s нет.', $jobClass));
    }

    protected function outboxEventCount(): int
    {
        return $this->getContainer()->get(DatabaseInterface::class)
            ->select()
            ->from($this->getContainer()->get(OutboxConfig::class)->eventsTableName)
            ->count(OutboxEventColumns::ID);
    }
}
