<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationRepository;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationActorTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Строка инбокса (канал database). Колонка actor хранит составной JSON-снимок автора и остаётся
 * на отдельном Typecast (NotificationActorTypecast) — тело класса собирает {id, name,
 * avatarMediaId} из JSON, это не сводится к одному вызову фабрики VO.
 */
#[Entity(
    role: 'notification',
    table: NotificationColumns::TABLE,
    repository: CycleNotificationRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CycleNotificationEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: NotificationColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: NotificationColumns::OUTBOX_ID)]
    public string $outboxId;

    #[Column(type: 'uuid', name: NotificationColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'string(255)', name: NotificationColumns::TYPE)]
    public string $type;

    #[Column(type: 'string(255)', name: NotificationColumns::TITLE)]
    public string $title;

    #[Column(type: 'text', name: NotificationColumns::BODY)]
    public string $body;

    #[Column(type: 'string(255)', name: NotificationColumns::ACTION_TYPE, nullable: true)]
    public string|null $actionType;

    #[Column(type: 'string(255)', name: NotificationColumns::ACTION_ID, nullable: true)]
    public string|null $actionId;

    #[Column(type: 'json', name: NotificationColumns::ACTOR, nullable: true, typecast: NotificationActorTypecast::class)]
    public NotificationActor $actor;

    #[Column(type: 'datetime', name: NotificationColumns::READ_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $readAt;
}
