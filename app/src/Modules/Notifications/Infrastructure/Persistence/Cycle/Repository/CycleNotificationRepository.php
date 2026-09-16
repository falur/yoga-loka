<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationEntity;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleNotificationEntity>
 */
final class CycleNotificationRepository extends AbstractRepository implements NotificationRepository
{
    /**
     * @param Select<CycleNotificationEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private NotificationMapper $notificationMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findByOutboxId(NotificationOutboxId $outboxId): Notification|null
    {
        /** @var CycleNotificationEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([NotificationColumns::OUTBOX_ID => $outboxId->value()]);

        return $cycleEntity === null ? null : $this->notificationMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIdForRecipient(NotificationId $id, UserId $userId): Notification|null
    {
        /** @var CycleNotificationEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([
            NotificationColumns::ID => $id->value(),
            NotificationColumns::USER_ID => $userId->value(),
        ]);

        return $cycleEntity === null ? null : $this->notificationMapper->toDomain($cycleEntity);
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md:32): id DESC, при наличии курсора берём строки
     * строго старше курсора. Вызывающий Query запрашивает limit+1 для вычисления nextCursor.
     */
    #[\Override]
    public function findPageForRecipient(UserId $userId, NotificationId|null $cursor, int $limit): NotificationCollection
    {
        $notificationCollection = new NotificationCollection();

        /** @var iterable<CycleNotificationEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(NotificationColumns::USER_ID, $userId->value())
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $notificationCollection->push($this->notificationMapper->toDomain($cycleEntity));
        }

        return $notificationCollection;
    }

    #[\Override]
    public function countUnreadForRecipient(UserId $userId): int
    {
        return $this->select()
            ->where(NotificationColumns::USER_ID, $userId->value())
            ->where(NotificationColumns::READ_AT, '=', null)
            ->count();
    }

    #[\Override]
    public function save(Notification $notification): void
    {
        /** @var CycleNotificationEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([NotificationColumns::ID => $notification->id->value()]);

        $this->entityManager
            ->persist($this->notificationMapper->toCycleEntity(
                notification: $notification,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }

    #[\Override]
    public function saveAll(NotificationCollection $notifications): void
    {
        foreach ($notifications as $notification) {
            /** @var CycleNotificationEntity|null $cycleEntity */
            $cycleEntity = $this->findOne([NotificationColumns::ID => $notification->id->value()]);

            $this->entityManager->persist($this->notificationMapper->toCycleEntity(
                notification: $notification,
                cycleEntity: $cycleEntity,
            ));
        }

        $this->entityManager->run();
    }
}
