<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\Repository;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;

/**
 * Хранение интеграционных событий. Корень агрегата — StoredOutboxEvent, внутренних сущностей
 * у него нет.
 */
interface StoredOutboxEventRepository
{
    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null;

    /**
     * Пачка событий, готовых к перекладке в очередь, с блокировкой строк.
     */
    public function findPendingForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxEventCollection;

    /**
     * Ставит событие в текущую запись без прогона: в базу оно уходит тем же прогоном, что и
     * бизнес-изменение источника, поэтому наружу не уйдёт факт, которого не случилось.
     */
    public function add(StoredOutboxEvent $storedOutboxEvent): void;

    /**
     * Сохраняет событие своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(StoredOutboxEvent $storedOutboxEvent): void;

    /**
     * Сохраняет пачку событий одним прогоном на всю пачку, а не по прогону на событие.
     */
    public function saveAll(OutboxEventCollection $storedOutboxEvents): void;
}
