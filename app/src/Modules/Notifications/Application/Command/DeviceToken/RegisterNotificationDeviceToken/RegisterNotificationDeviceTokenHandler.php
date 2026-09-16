<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\Exception\UnknownDevicePlatformException;
use App\Shared\Domain\ValueObject\UserId;
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
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RegisterNotificationDeviceTokenCommand $command): NotificationDeviceToken
    {
        $userId = UserId::fromString($command->userId);
        $token = DeviceToken::fromString($command->token);
        $platform = DevicePlatform::tryFrom($command->platform)
            ?? throw new UnknownDevicePlatformException();

        $deviceToken = $this->notificationDeviceTokenRepository->findByToken($token);

        if ($deviceToken === null) {
            $deviceToken = NotificationDeviceToken::create(userId: $userId, token: $token, platform: $platform);
        } else {
            $deviceToken->reassignTo(userId: $userId, platform: $platform);
        }

        $this->notificationDeviceTokenRepository->save($deviceToken);

        $this->logger->debug(message: 'Push-токен зарегистрирован.', context: [
            'deviceTokenId' => $deviceToken->id->value(),
            'userId' => $command->userId,
            'platform' => $platform->value,
        ]);

        return $deviceToken;
    }
}
