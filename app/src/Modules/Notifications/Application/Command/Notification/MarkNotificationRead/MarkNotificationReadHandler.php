<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead;

use App\Modules\Notifications\Application\View\NotificationView;
use App\Modules\Notifications\Application\View\NotificationViewAssembler;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class MarkNotificationReadHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationViewAssembler $notificationViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(MarkNotificationReadCommand $command): NotificationView
    {
        $notification = $this->notificationRepository->findByIdForRecipient(
            id: NotificationId::fromString($command->notificationId),
            userId: UserId::fromString($command->userId),
        ) ?? throw new NotFoundException('app.notifications.not_found');

        $notification->markRead(new \DateTimeImmutable());
        $this->entityManager->persist($notification);
        $this->entityManager->run();

        $this->logger->debug(message: 'Уведомление отмечено прочитанным.', context: [
            'notificationId' => $notification->id->value(),
            'userId' => $command->userId,
        ]);

        // Отдаём обогащённый read-model (аватар автора собирается в MediaDto на чтении), а не доменную
        // сущность: форма ответа совпадает со списком инбокса.
        return $this->notificationViewAssembler->fromNotification($notification);
    }
}
