<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Регистрация push-токена. Токен уникален: если он уже есть — переустанавливаем владельца и
 * платформу (устройство сменило аккаунт), иначе создаём новую строку.
 */
final readonly class RegisterNotificationDeviceTokenHandler
{
    public function __construct(
        private NotificationDeviceTokenRepository $notificationDeviceTokenRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RegisterNotificationDeviceTokenCommand $command): NotificationDeviceToken
    {
        $userId = UserId::fromString($command->userId);
        $token = DeviceToken::fromString($command->token);
        $platform = DevicePlatform::tryFrom($command->platform)
            ?? throw new ValidationException('app.notifications.unknown_platform');

        $deviceToken = $this->notificationDeviceTokenRepository->findByToken($token);

        if ($deviceToken === null) {
            $deviceToken = NotificationDeviceToken::create(userId: $userId, token: $token, platform: $platform);
        } else {
            $deviceToken->reassignTo(userId: $userId, platform: $platform);
        }

        $this->entityManager->persist($deviceToken);
        $this->entityManager->run();

        $this->logger->debug(message: 'Push-токен зарегистрирован.', context: [
            'deviceTokenId' => $deviceToken->id->value(),
            'userId' => $command->userId,
            'platform' => $platform->value,
        ]);

        return $deviceToken;
    }
}
