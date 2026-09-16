<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<StoredOutboxEvent>
 */
final class CycleStoredOutboxEventRepository extends AbstractRepository implements StoredOutboxEventRepository
{
    /**
     * @param Select<StoredOutboxEvent> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null
    {
        return $this->findByPK($outboxEventId->value());
    }

    #[\Override]
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

    #[\Override]
    public function add(StoredOutboxEvent $storedOutboxEvent): void
    {
        $this->entityManager->persist($storedOutboxEvent);
    }

    #[\Override]
    public function save(StoredOutboxEvent $storedOutboxEvent): void
    {
        $this->entityManager
            ->persist($storedOutboxEvent)
            ->run();
    }

    #[\Override]
    public function saveAll(OutboxEventCollection $storedOutboxEvents): void
    {
        foreach ($storedOutboxEvents as $storedOutboxEvent) {
            $this->entityManager->persist($storedOutboxEvent);
        }

        $this->entityManager->run();
    }
}
