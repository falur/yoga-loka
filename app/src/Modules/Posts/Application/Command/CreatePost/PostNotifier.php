<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

use App\Modules\Notifications\Public\Contract\NotificationContract;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Posts\Domain\ValueObject\PostNotificationAction;
use App\Modules\User\Public\Dto\UserProfileDto;
use Psr\Log\LoggerInterface;

/**
 * Стейджит одно уведомление модуля Posts получателю. Самодействие не уведомляет
 * (recipient == actor -> send() не вызывается). Текст рендерится в локали получателя через
 * NotificationContentBuilder, отправка — через публичный контракт Notifications (событие пишется
 * в outbox сразу, в транзакцию вызывающего Handler-а). Профиль автора и deep-link перекладываются
 * в публичные DTO автора и перехода здесь, чтобы билдер принимал их цельными объектами.
 */
final readonly class PostNotifier
{
    public function __construct(
        private NotificationContract $notifications,
        private NotificationContentBuilder $contentBuilder,
        private LoggerInterface $logger,
    ) {}

    public function notify(
        PostNotificationType $type,
        UserProfileDto $actor,
        UserProfileDto $recipient,
        PostNotificationAction $action,
    ): void {
        if ($actor->userId === $recipient->userId) {
            return;
        }

        $this->notifications->send(
            recipientUserId: $recipient->userId,
            content: $this->contentBuilder->build(
                type: $type,
                actor: $this->actorSnapshot($actor),
                action: new NotificationActionDto(actionType: $action->target->value, actionId: $action->id),
                recipientLocale: $recipient->locale,
            ),
        );

        $this->logger->debug(message: 'Уведомление поставлено в очередь.', context: [
            'typeCode' => $type->value,
            'recipientId' => $recipient->userId,
            'actionId' => $action->id,
        ]);
    }

    private function actorSnapshot(UserProfileDto $actor): NotificationActorDto
    {
        return new NotificationActorDto(
            id: $actor->userId,
            name: $actor->name,
            // Аватар опционален: нет аватара (avatar = null) -> уведомление несёт автора без аватара,
            // клиент подставит заглушку сам. Храним id медиа, а не ссылку: полное медиа (оригинал +
            // конверсии) потребитель соберёт заново на границе показа, поэтому ссылка не протухнет.
            avatarMediaId: $actor->avatar?->id,
        );
    }
}
