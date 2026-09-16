<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class NotificationMapper
{
    public function toDomain(CycleNotificationEntity $cycleEntity): Notification
    {
        return Notification::restore(
            id: NotificationId::fromString($cycleEntity->id),
            outboxId: NotificationOutboxId::fromString($cycleEntity->outboxId),
            userId: UserId::fromString($cycleEntity->userId),
            type: NotificationTypeCode::fromString($cycleEntity->type),
            title: NotificationTitle::fromString($cycleEntity->title),
            body: NotificationBody::fromString($cycleEntity->body),
            actionType: $this->notificationActionType($cycleEntity->actionType),
            actionId: $this->notificationActionId($cycleEntity->actionId),
            actor: $cycleEntity->actor,
            readState: $this->notificationReadState($cycleEntity->readAt),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Notification $notification,
        CycleNotificationEntity|null $cycleEntity = null,
    ): CycleNotificationEntity {
        $cycleEntity ??= new CycleNotificationEntity();
        $cycleEntity->id = $notification->id->value();
        $cycleEntity->outboxId = $notification->outboxId->value();
        $cycleEntity->userId = $notification->userId->value();
        $cycleEntity->type = $notification->type->value();
        $cycleEntity->title = $notification->title->value();
        $cycleEntity->body = $notification->body->value();
        $cycleEntity->actionType = $notification->actionType->value();
        $cycleEntity->actionId = $notification->actionId->value();
        $cycleEntity->actor = $notification->actor;
        $cycleEntity->readAt = $notification->readState->value();
        $cycleEntity->createdAt = $notification->createdAt;
        $cycleEntity->updatedAt = $notification->updatedAt;

        return $cycleEntity;
    }

    /**
     * Null-bridging для колонки action_type: логика перенесена без изменений из удалённого
     * NotificationActionTypeTypecast::castDatabaseValue().
     */
    private function notificationActionType(string|null $value): NotificationActionType
    {
        return $value === null ? NotificationActionType::none() : NotificationActionType::of($value);
    }

    /**
     * Null-bridging для колонки action_id: логика перенесена без изменений из удалённого
     * NotificationActionIdTypecast::castDatabaseValue().
     */
    private function notificationActionId(string|null $value): NotificationActionId
    {
        return $value === null ? NotificationActionId::none() : NotificationActionId::of($value);
    }

    /**
     * Null-bridging для колонки read_at: логика перенесена без изменений из удалённого
     * NotificationReadStateTypecast::castDatabaseValue() (ветка defensive-конверсии из
     * не-Immutable DateTime/строки не переносится: встроенный typecast: 'datetime' Cycle уже
     * гарантирует \DateTimeImmutable|null на поле readAt — тот же приём, что для датных полей
     * в предыдущих фазах волны).
     */
    private function notificationReadState(\DateTimeImmutable|null $value): NotificationReadState
    {
        return $value === null ? NotificationReadState::unread() : NotificationReadState::readAt($value);
    }
}
