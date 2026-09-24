<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\RequestNotification;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Лёгкий триггер отправки: валидирует вид через каталог (fail-fast) и пишет один
 * NotificationRequestedEvent в outbox. Сценарий вызывается из транзакции источника, поэтому его
 * #[Transactional] вложенный и даёт SAVEPOINT. EntityManager::run() здесь не вызывается и не
 * нужен: хранилище пакета вставляет строку события сразу, а в одну транзакцию с данными
 * источника её сводит внешняя транзакция.
 */
final readonly class RequestNotificationHandler
{
    public function __construct(
        private NotificationTypeCatalogContract $typeCatalog,
        private OutboxEventStoreContract $outboxEventStore,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    public function handle(RequestNotificationCommand $command): void
    {
        // fail-fast: незарегистрированный вид -> исключение до стейджинга события.
        $this->typeCatalog->get($command->type);

        $this->logger->debug(message: 'Триггер уведомления.', context: [
            'userId' => $command->recipient->value(),
            'type' => $command->type->value(),
        ]);

        $outboxEventId = $this->outboxEventStore->add(new NotificationRequestedEvent(
            userId: $command->recipient->value(),
            type: $command->type->value(),
            title: $command->title->value(),
            body: $command->body->value(),
            action: $this->actionPayload($command->action),
            actor: $this->actorPayload($command->actor),
            createdAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ));

        $this->logger->debug(message: 'NotificationRequested застейджен.', context: [
            'outboxId' => $outboxEventId,
            'type' => $command->type->value(),
        ]);
    }

    private function actionPayload(NotificationAction $action): NotificationActionDto|null
    {
        if (!$action->hasLink()) {
            return null;
        }

        return new NotificationActionDto(
            actionType: $action->actionType()->presentValue(),
            actionId: $action->actionId()->presentValue(),
        );
    }

    private function actorPayload(NotificationActor $actor): NotificationActorDto|null
    {
        if (!$actor->isPresent()) {
            return null;
        }

        return new NotificationActorDto(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatarMediaId: $actor->presentAvatarMediaId(),
        );
    }
}
