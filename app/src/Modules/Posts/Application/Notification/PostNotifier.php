<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Contract\NotificationSenderContract;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Стейджит одно уведомление модуля Posts получателю. Самодействие не уведомляет
 * (recipient == actor -> send() не вызывается). Текст рендерится в локали получателя через
 * NotificationContentBuilder, отправка — через NotificationSenderContract (стейджинг в outbox,
 * flush делает вызывающий Handler своим run()). Профиль автора и deep-link перекладываются в снимок
 * NotificationActor и переход NotificationAction здесь, чтобы билдер принимал их цельными объектами.
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
        PostNotificationAction $action,
    ): void {
        if ($actor->userId === $recipient->userId) {
            return;
        }

        $this->notificationSender->send(
            recipient: UserId::fromString($recipient->userId),
            content: $this->contentBuilder->build(
                type: $type,
                actor: $this->actorSnapshot($actor),
                action: NotificationAction::linkTo(actionType: $action->target->value, actionId: $action->id),
                recipientLocale: $recipient->locale,
            ),
        );

        $this->logger->debug(message: 'Уведомление поставлено в очередь.', context: [
            'typeCode' => $type->value,
            'recipientId' => $recipient->userId,
            'actionId' => $action->id,
        ]);
    }

    private function actorSnapshot(UserPublicProfileView $actor): NotificationActor
    {
        return NotificationActor::of(
            userId: UserId::fromString($actor->userId),
            name: $actor->name,
            // Аватар опционален: нет аватара (avatar = null) -> уведомление несёт автора без аватара,
            // клиент подставит заглушку сам. Храним id медиа, а не ссылку: полный MediaView (оригинал +
            // конверсии) потребитель соберёт заново на границе показа, поэтому ссылка не протухнет.
            avatarMediaId: $actor->avatar?->id,
        );
    }
}
