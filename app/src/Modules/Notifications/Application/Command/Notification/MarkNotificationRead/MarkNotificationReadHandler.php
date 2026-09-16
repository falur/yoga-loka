<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Notifications\Application\Result\NotificationResult;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Exception\NotificationNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class MarkNotificationReadHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MediaContract $media,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(MarkNotificationReadCommand $command): NotificationResult
    {
        $notification = $this->notificationRepository->findByIdForRecipient(
            id: NotificationId::fromString($command->notificationId),
            userId: UserId::fromString($command->userId),
        ) ?? throw new NotificationNotFoundException();

        $notification->markRead(new \DateTimeImmutable());
        $this->notificationRepository->save($notification);

        $this->logger->debug(message: 'Уведомление отмечено прочитанным.', context: [
            'notificationId' => $notification->id->value(),
            'userId' => $command->userId,
        ]);

        // Отдаём обогащённый ответ (аватар автора собирается в MediaDto на чтении), а не доменную
        // сущность: форма ответа совпадает со списком инбокса.
        return NotificationResult::fromNotification(notification: $notification, avatars: $this->resolveAvatar($notification));
    }

    private function resolveAvatar(Notification $notification): MediaDtoCollection
    {
        if (!$notification->actor->isPresent()) {
            return new MediaDtoCollection();
        }

        $avatarMediaId = $notification->actor->presentAvatarMediaId();

        if ($avatarMediaId === null) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds([$avatarMediaId]);
    }
}
