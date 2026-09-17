<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Columns\StoredOutboxEventColumns;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Entity\CycleStoredOutboxEventEntity;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Mapper\StoredOutboxEventMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleStoredOutboxEventEntity>
 */
final class CycleStoredOutboxEventRepository extends AbstractRepository implements StoredOutboxEventRepository
{
    /**
     * @param Select<CycleStoredOutboxEventEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private StoredOutboxEventMapper $storedOutboxEventMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null
    {
        /** @var CycleStoredOutboxEventEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($outboxEventId->value());

        return $cycleEntity === null ? null : $this->storedOutboxEventMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findPendingForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxEventCollection {
        $outboxEventCollection = new OutboxEventCollection();

        /** @var iterable<CycleStoredOutboxEventEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(StoredOutboxEventColumns::STATUS, 'in', new Parameter([
                OutboxEventStatus::Pending->value,
                OutboxEventStatus::Publishing->value,
            ]))
            ->where(StoredOutboxEventColumns::AVAILABLE_AT, '<=', $now)
            // Порядок выборки согласован с составным индексом (status, available_at, id):
            // сначала по времени доступности (естественный порядок relay), затем id
            // (UUID v7, хронологический) как стабильный tie-breaker.
            ->orderBy([
                StoredOutboxEventColumns::AVAILABLE_AT => 'ASC',
                StoredOutboxEventColumns::ID => 'ASC',
            ])
            ->forUpdate()
            ->limit($outboxRelayBatchSize->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $outboxEventCollection->push($this->storedOutboxEventMapper->toDomain($cycleEntity));
        }

        return $outboxEventCollection;
    }

    #[\Override]
    public function add(StoredOutboxEvent $storedOutboxEvent): void
    {
        /** @var CycleStoredOutboxEventEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([StoredOutboxEventColumns::ID => $storedOutboxEvent->id->value()]);

        $this->entityManager->persist($this->storedOutboxEventMapper->toCycleEntity(
            storedOutboxEvent: $storedOutboxEvent,
            cycleEntity: $cycleEntity,
        ));
    }

    #[\Override]
    public function save(StoredOutboxEvent $storedOutboxEvent): void
    {
        /** @var CycleStoredOutboxEventEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([StoredOutboxEventColumns::ID => $storedOutboxEvent->id->value()]);

        $this->entityManager
            ->persist($this->storedOutboxEventMapper->toCycleEntity(
                storedOutboxEvent: $storedOutboxEvent,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }

    #[\Override]
    public function saveAll(OutboxEventCollection $storedOutboxEvents): void
    {
        foreach ($storedOutboxEvents as $storedOutboxEvent) {
            /** @var CycleStoredOutboxEventEntity|null $cycleEntity */
            $cycleEntity = $this->findOne([StoredOutboxEventColumns::ID => $storedOutboxEvent->id->value()]);

            $this->entityManager->persist($this->storedOutboxEventMapper->toCycleEntity(
                storedOutboxEvent: $storedOutboxEvent,
                cycleEntity: $cycleEntity,
            ));
        }

        $this->entityManager->run();
    }
}
