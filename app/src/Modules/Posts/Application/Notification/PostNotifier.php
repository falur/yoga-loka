<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Contract\NotificationSenderContract;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Стейджит одно уведомление модуля Posts получателю. Самодействие не уведомляет
 * (recipient == actor -> send() не вызывается). Текст рендерится в локали получателя через
 * NotificationContentBuilder, отправка — через NotificationSenderContract (стейджинг в outbox,
 * flush делает вызывающий Handler своим run()).
 */
final readonly class PostNotifier
{
    public function __construct(
        private NotificationSenderContract $notificationSender,
        private NotificationContentBuilder $contentBuilder,
        private LoggerInterface $logger,
    ) {}

    public function notify(
        PostNotificationType $type,
        UserPublicProfileView $actor,
        UserPublicProfileView $recipient,
        string $actionType,
        string $actionId,
    ): void {
        if ($actor->userId === $recipient->userId) {
            return;
        }

        $this->notificationSender->send(
            recipient: UserId::fromString($recipient->userId),
            content: $this->contentBuilder->build(
                type: $type,
                actorUserId: UserId::fromString($actor->userId),
                actorName: $actor->name,
                actorAvatarUrl: $actor->avatarUrl,
                recipientLocale: $recipient->locale,
                actionType: $actionType,
                actionId: $actionId,
            ),
        );

        $this->logger->debug(message: 'Уведомление поставлено в очередь.', context: [
            'typeCode' => $type->value,
            'recipientId' => $recipient->userId,
            'actionId' => $actionId,
        ]);
    }
}
