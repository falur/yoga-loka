<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Notification>
 */
final class CycleNotificationRepository extends AbstractRepository implements NotificationRepository
{
    /**
     * @param Select<Notification> $select
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
    public function findByOutboxId(NotificationOutboxId $outboxId): Notification|null
    {
        return $this->findOne(['outbox_id' => $outboxId->value()]);
    }

    #[\Override]
    public function findByIdForRecipient(NotificationId $id, UserId $userId): Notification|null
    {
        return $this->findOne(['id' => $id->value(), 'user_id' => $userId->value()]);
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md:32): id DESC, при наличии курсора берём строки
     * строго старше курсора. Вызывающий Query запрашивает limit+1 для вычисления nextCursor.
     */
    #[\Override]
    public function findPageForRecipient(UserId $userId, NotificationId|null $cursor, int $limit): NotificationCollection
    {
        return new NotificationCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function countUnreadForRecipient(UserId $userId): int
    {
        return $this->select()
            ->where('user_id', $userId->value())
            ->where('read_at', '=', null)
            ->count();
    }

    #[\Override]
    public function save(Notification $notification): void
    {
        $this->entityManager
            ->persist($notification)
            ->run();
    }

    #[\Override]
    public function saveAll(NotificationCollection $notifications): void
    {
        foreach ($notifications as $notification) {
            $this->entityManager->persist($notification);
        }

        $this->entityManager->run();
    }
}
