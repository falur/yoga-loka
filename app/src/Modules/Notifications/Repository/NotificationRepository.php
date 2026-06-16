<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Cycle\WhenSelect;

/**
 * @extends AbstractRepository<Notification>
 */
final class NotificationRepository extends AbstractRepository
{
    public function findByOutboxId(NotificationOutboxId $outboxId): Notification|null
    {
        return $this->findOne(['outbox_id' => $outboxId->value()]);
    }

    public function findByIdForRecipient(NotificationId $id, UserId $userId): Notification|null
    {
        return $this->findOne(['id' => $id->value(), 'user_id' => $userId->value()]);
    }

    /**
     * Cursor-пагинация по UUID v7 id (rules.md:32): id DESC, при наличии курсора берём строки
     * строго старше курсора. Вызывающий Query запрашивает limit+1 для вычисления nextCursor.
     */
    public function findPageForRecipient(UserId $userId, NotificationId|null $cursor, int $limit): NotificationCollection
    {
        $cursorId = $cursor?->value();

        return new NotificationCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->when(
                    condition: $cursorId !== null,
                    callback: static function (WhenSelect $query) use ($cursorId): void {
                        $query->where('id', '<', $cursorId);
                    },
                )
                ->orderBy(expression: 'id', direction: 'DESC')
                ->limit($limit)
                ->fetchAll(),
        );
    }

    public function countUnreadForRecipient(UserId $userId): int
    {
        return $this->select()
            ->where('user_id', $userId->value())
            ->where('read_at', '=', null)
            ->count();
    }
}
