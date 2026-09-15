<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\PublicApi;

use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationCommand;
use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationHandler;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Contract\NotificationContract;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Public\Dto\NotificationContentDto;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;

/**
 * Входной адаптер публичного контракта Notifications: собирает из публичного DTO доменные значения
 * модуля (отсутствие перехода и автора — none()) и раскладывает вызов в сценарий отправки. Правил
 * здесь нет: проверку вида, журнал и стейджинг события ведёт сценарий.
 *
 * Отправка диспатчится командной шиной, поэтому внутри транзакции источника остаётся вложенной
 * (#[Transactional] -> SAVEPOINT) и своего flush не делает.
 */
final readonly class NotificationProvider implements NotificationContract
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private RequestNotificationHandler $requestNotificationHandler,
    ) {}

    #[\Override]
    public function send(string $recipientUserId, NotificationContentDto $content): void
    {
        $this->commandBus->dispatch(
            command: new RequestNotificationCommand(
                recipient: UserId::fromString($recipientUserId),
                type: NotificationTypeCode::fromString($content->typeCode),
                title: NotificationTitle::fromString($content->title),
                body: NotificationBody::fromString($content->body),
                action: $this->action($content->action),
                actor: $this->actor($content->actor),
            ),
            handler: $this->requestNotificationHandler->handle(...),
        );
    }

    private function action(NotificationActionDto|null $action): NotificationAction
    {
        if ($action === null) {
            return NotificationAction::none();
        }

        return NotificationAction::linkTo(actionType: $action->actionType, actionId: $action->actionId);
    }

    private function actor(NotificationActorDto|null $actor): NotificationActor
    {
        if ($actor === null) {
            return NotificationActor::none();
        }

        return NotificationActor::of(
            userId: UserId::fromString($actor->id),
            name: $actor->name,
            avatarMediaId: $actor->avatarMediaId,
        );
    }
}
