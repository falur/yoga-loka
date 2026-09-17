<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\Exception\NotificationDeviceTokenNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Удаление push-токена текущего пользователя по значению токена. Поиск скоупится по владельцу,
 * чтобы нельзя было удалить чужой токен; токен не найден у пользователя ->
 * NotificationDeviceTokenNotFoundException (404).
 */
final readonly class RemoveNotificationDeviceTokenHandler
{
    public function __construct(
        private NotificationDeviceTokenRepository $notificationDeviceTokenRepository,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RemoveNotificationDeviceTokenCommand $command): NotificationDeviceToken
    {
        $deviceToken = $this->notificationDeviceTokenRepository->findByTokenForUser(
            token: DeviceToken::fromString($command->token),
            userId: UserId::fromString($command->userId),
        ) ?? throw new NotificationDeviceTokenNotFoundException();

        $this->notificationDeviceTokenRepository->delete($deviceToken);

        $this->logger->debug(message: 'Push-токен удалён.', context: [
            'deviceTokenId' => $deviceToken->id->value(),
        ]);

        return $deviceToken;
    }
}
