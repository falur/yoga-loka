<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Shared\Infrastructure\Database\DatabaseDateTimeFormat;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\Select;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<StoredOutboxEvent>
 */
final class OutboxEventRepository extends Repository
{
    public function __construct(
        Select $select,
        private readonly DatabaseInterface $database,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($select);
    }

    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null
    {
        $outboxEvent = $this->findByPK($outboxEventId->value());

        return $outboxEvent instanceof StoredOutboxEvent ? $outboxEvent : null;
    }

    public function findPendingForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxEventCollection {
        return new OutboxEventCollection(
            $this->storedOutboxEvents(
                objects: $this->pendingForRelayEventsQuery(now: $now)
                    ->limit($outboxRelayBatchSize->value())
                    ->fetchAll(),
            ),
        );
    }

    /**
     * Читает pending-строки до ORM-гидрации и парсит их в типизированные
     * OutboxPendingRow, чтобы orchestrator мог отловить битые данные.
     *
     * Query Builder вместо ORM Select: ORM Select гидрирует строку через typecast
     * и падает на повреждённой записи, не давая выделить конкретную строку для
     * пометки failed. Парсинг здесь не мутирует данные и не решает «строка битая →
     * failed» — это остаётся за orchestrator-ом.
     *
     * Выборка ограничена batchSize так же, как обычная findPendingForRelay: recovery
     * вызывается из того же batch-контекста, поэтому за один прогон гасится до batchSize
     * битых строк, а большой backlog не читается в память целиком.
     */
    public function pendingForRelayRows(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxPendingRowCollection {
        $databaseRows = $this->database
            ->select([
                'id',
                'type',
                'payload',
                'status',
                'attempts',
                'available_at',
                'queued_at',
                'handled_at',
                'failed_at',
                'last_error',
            ])
            ->from('outbox_events')
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
            ->limit($outboxRelayBatchSize->value())
            ->fetchAll();

        $pendingRows = new OutboxPendingRowCollection();

        foreach ($databaseRows as $databaseRow) {
            if (!\is_array($databaseRow)) {
                continue;
            }

            $pendingRows->push(OutboxPendingRow::fromDatabaseRow($databaseRow));
        }

        return $pendingRows;
    }

    /**
     * Помечает строку failed по сырому id строки БД. Id принимается строкой, потому что
     * вызывается для повреждённых строк, чей id может не пройти доменную валидацию
     * (граница системы — идентификатор строки БД, а не доменный VO).
     *
     * Возвращает число реально затронутых строк: CAS может не затронуть ни одной (id не
     * совпал или строку уже увели из pending/publishing гонкой статуса). По этому числу
     * recovery в relay отличает фактический прогресс от ложной предпосылки «распознал → пометил».
     */
    public function markRowFailedById(
        string $outboxEventId,
        OutboxLastError $lastError,
        \DateTimeImmutable $now,
    ): int {
        // Атомарный CAS-переход в failed: помечаем только если строка всё ещё pending/publishing,
        // ORM Select такой условный UPDATE по статусу выразить не может.
        return $this->database
            ->update('outbox_events')
            ->values([
                'status' => OutboxEventStatus::Failed->value,
                'failed_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'last_error' => $lastError->toDatabaseValue(),
                'updated_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->where('id', $outboxEventId)
            ->where('status', 'in', new Parameter([
                OutboxEventStatus::Pending->value,
                OutboxEventStatus::Publishing->value,
            ]))
            ->run();
    }

    public function save(StoredOutboxEvent $outboxEvent): void
    {
        $this->entityManager->persist($outboxEvent);
    }

    public function markQueuedIfPublishing(OutboxEventId $outboxEventId, \DateTimeImmutable $now): bool
    {
        // Атомарный CAS-переход publishing -> queued: ORM Select не выражает условный UPDATE по статусу.
        return $this->database
            ->update('outbox_events')
            ->values([
                'status' => OutboxEventStatus::Queued->value,
                'queued_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
                'last_error' => null,
                'updated_at' => $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS),
            ])
            ->where('id', $outboxEventId->value())
            ->where('status', OutboxEventStatus::Publishing->value)
            ->run() === 1;
    }

    public function findFreshStatusById(OutboxEventId $outboxEventId): OutboxEventStatus|null
    {
        // Sync Job может поменять статус во время push, а identity map ORM всё ещё держит старую Entity.
        $row = $this->database
            ->select('status')
            ->from('outbox_events')
            ->where('id', $outboxEventId->value())
            ->limit(1)
            ->fetchAll()[0] ?? null;

        if ($row === null) {
            return null;
        }

        if (!\is_array($row) || !\is_string($row['status'] ?? null)) {
            throw new \UnexpectedValueException('Запрос outbox-события вернул строку без статуса.');
        }

        return OutboxEventStatus::from($row['status']);
    }

    /**
     * @param list<object> $objects
     * @return list<StoredOutboxEvent>
     */
    private function storedOutboxEvents(array $objects): array
    {
        $storedOutboxEvents = [];

        foreach ($objects as $object) {
            if (!$object instanceof StoredOutboxEvent) {
                throw new \UnexpectedValueException('Запрос pending outbox-событий вернул объект неверного типа.');
            }

            $storedOutboxEvents[] = $object;
        }

        return $storedOutboxEvents;
    }

    /**
     * @return Select<StoredOutboxEvent>
     */
    private function pendingForRelayEventsQuery(\DateTimeImmutable $now): Select
    {
        return $this->select()
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
            ->forUpdate();
    }
}
