<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Удаление push-токена текущего пользователя по значению токена. Поиск скоупится по владельцу,
 * чтобы нельзя было удалить чужой токен; токен не найден у пользователя -> NotFoundException.
 */
final readonly class RemoveNotificationDeviceTokenHandler
{
    public function __construct(
        private NotificationDeviceTokenRepository $notificationDeviceTokenRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RemoveNotificationDeviceTokenCommand $command): NotificationDeviceToken
    {
        $deviceToken = $this->notificationDeviceTokenRepository->findByTokenForUser(
            token: DeviceToken::fromString($command->token),
            userId: UserId::fromString($command->userId),
        ) ?? throw new NotFoundException('app.notifications.device_token_not_found');

        $this->entityManager->delete($deviceToken);
        $this->entityManager->run();

        $this->logger->debug(message: 'Push-токен удалён.', context: [
            'deviceTokenId' => $deviceToken->id->value(),
        ]);

        return $deviceToken;
    }
}
