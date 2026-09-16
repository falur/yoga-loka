<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Строка инбокса (канал database) — создаётся фоновой рассылкой. Deep-link хранится в двух
 * nullable-колонках action_type/action_id и собирается доменным методом action().
 */
final class Notification
{
    use HasTimestamps;

    public private(set) NotificationId $id;

    public private(set) NotificationOutboxId $outboxId;

    public private(set) UserId $userId;

    public private(set) NotificationTypeCode $type;

    public private(set) NotificationTitle $title;

    public private(set) NotificationBody $body;

    public private(set) NotificationActionType $actionType;

    public private(set) NotificationActionId $actionId;

    public private(set) NotificationActor $actor;

    public private(set) NotificationReadState $readState;

    public static function create(
        NotificationOutboxId $outboxId,
        UserId $userId,
        NotificationTypeCode $type,
        NotificationTitle $title,
        NotificationBody $body,
        NotificationAction $action,
        NotificationActor $actor,
        \DateTimeImmutable $triggeredAt,
    ): self {
        $notification = new self();
        $notification->id = NotificationId::generate();
        $notification->outboxId = $outboxId;
        $notification->userId = $userId;
        $notification->type = $type;
        $notification->title = $title;
        $notification->body = $body;
        $notification->actionType = $action->actionType();
        $notification->actionId = $action->actionId();
        $notification->actor = $actor;
        $notification->readState = NotificationReadState::unread();
        $notification->initializeTimestamps($triggeredAt);

        return $notification;
    }

    public static function restore(
        NotificationId $id,
        NotificationOutboxId $outboxId,
        UserId $userId,
        NotificationTypeCode $type,
        NotificationTitle $title,
        NotificationBody $body,
        NotificationActionType $actionType,
        NotificationActionId $actionId,
        NotificationActor $actor,
        NotificationReadState $readState,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $notification = new self();
        $notification->id = $id;
        $notification->outboxId = $outboxId;
        $notification->userId = $userId;
        $notification->type = $type;
        $notification->title = $title;
        $notification->body = $body;
        $notification->actionType = $actionType;
        $notification->actionId = $actionId;
        $notification->actor = $actor;
        $notification->readState = $readState;
        $notification->createdAt = $createdAt;
        $notification->updatedAt = $updatedAt;

        return $notification;
    }

    /**
     * Доменный метод (не property-hook): собирает переход из двух backing-колонок.
     */
    public function action(): NotificationAction
    {
        return NotificationAction::fromParts(actionType: $this->actionType, actionId: $this->actionId);
    }

    public function markRead(\DateTimeImmutable $readAt): void
    {
        if ($this->readState->isRead()) {
            return;
        }

        $this->readState = NotificationReadState::readAt($readAt);
        $this->touch($readAt);
    }

    public function isRead(): bool
    {
        return $this->readState->isRead();
    }
}
