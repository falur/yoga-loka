<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<StoredOutboxEvent>
 */
final class OutboxEventRepository extends AbstractRepository
{
    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null
    {
        return $this->findByPK($outboxEventId->value());
    }

    public function findPendingForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxEventCollection {
        return new OutboxEventCollection(
            $this->select()
                ->where('status', 'in', new Parameter([
                    OutboxEventStatus::Pending->value,
                    OutboxEventStatus::Publishing->value,
                ]))
                ->where('available_at', '<=', $now)
                // Порядок выборки согласован с составным индексом (status, available_at, id):
                // сначала по времени доступности (естественный порядок relay), затем id
                // (UUID v7, хронологический) как стабильный tie-breaker.
                ->orderBy([
                    'available_at' => 'ASC',
                    'id' => 'ASC',
                ])
                ->forUpdate()
                ->limit($outboxRelayBatchSize->value())
                ->fetchAll(),
        );
    }
}
