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
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationActionIdTypecast;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationActionTypeTypecast;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationActorTypecast;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationReadStateTypecast;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Строка инбокса (канал database) — создаётся фоновой рассылкой. Deep-link хранится в двух
 * nullable-колонках action_type/action_id и собирается доменным методом action().
 */
#[Entity(
    role: 'notification',
    table: 'notifications',
    repository: NotificationRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Notification
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: NotificationId::class)]
    public private(set) NotificationId $id;

    #[Column(type: 'uuid', name: 'outbox_id', typecast: NotificationOutboxId::class)]
    public private(set) NotificationOutboxId $outboxId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'string(255)', typecast: NotificationTypeCode::class)]
    public private(set) NotificationTypeCode $type;

    #[Column(type: 'string(255)', typecast: NotificationTitle::class)]
    public private(set) NotificationTitle $title;

    #[Column(type: 'text', typecast: NotificationBody::class)]
    public private(set) NotificationBody $body;

    #[Column(type: 'string(255)', name: 'action_type', nullable: true, typecast: NotificationActionTypeTypecast::class)]
    public private(set) NotificationActionType $actionType;

    #[Column(type: 'string(255)', name: 'action_id', nullable: true, typecast: NotificationActionIdTypecast::class)]
    public private(set) NotificationActionId $actionId;

    #[Column(type: 'json', name: 'actor', nullable: true, typecast: NotificationActorTypecast::class)]
    public private(set) NotificationActor $actor;

    #[Column(type: 'datetime', name: 'read_at', nullable: true, typecast: NotificationReadStateTypecast::class)]
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
