<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Contract\NotificationSenderContract;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Notifications\Application\Dto\NotificationContent;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Лёгкий триггер отправки: валидирует вид через реестр (fail-fast) и стейджит один
 * NotificationRequested в outbox. EntityManager::run() не вызывает — flush делает Handler
 * модуля-источника, у которого этот вызов внутри #[Transactional].
 */
final readonly class NotificationSender implements NotificationSenderContract
{
    public function __construct(
        private NotificationTypeRegistryContract $typeRegistry,
        private OutboxEventStoreContract $outboxEventStore,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function send(UserId $recipient, NotificationContent $content): void
    {
        // Код вида берём из переданного определения — на месте отправки строки-кода нет.
        $typeCode = $content->type->code();

        // fail-fast: незарегистрированный вид -> исключение до стейджинга события.
        $this->typeRegistry->get($typeCode);

        $this->logger->debug(message: 'Триггер уведомления.', context: [
            'userId' => $recipient->value(),
            'type' => $typeCode->value(),
        ]);

        $storedOutboxEventId = $this->outboxEventStore->add(new NotificationRequested(
            userId: $recipient->value(),
            type: $typeCode->value(),
            title: $content->title->value(),
            body: $content->body->value(),
            action: $this->actionPayload($content->action),
            actor: $this->actorPayload($content->actor),
            createdAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ));

        $this->logger->debug(message: 'NotificationRequested застейджен.', context: [
            'outboxId' => $storedOutboxEventId->value(),
            'type' => $typeCode->value(),
        ]);
    }

    private function actionPayload(NotificationAction $action): NotificationActionPayload|null
    {
        if (!$action->hasLink()) {
            return null;
        }

        return new NotificationActionPayload(
            actionType: $action->actionType()->presentValue(),
            actionId: $action->actionId()->presentValue(),
        );
    }

    private function actorPayload(NotificationActor $actor): NotificationActorPayload|null
    {
        if (!$actor->isPresent()) {
            return null;
        }

        return new NotificationActorPayload(
            id: $actor->presentId(),
            name: $actor->presentName(),
            avatarMediaId: $actor->presentAvatarMediaId(),
        );
    }
}
