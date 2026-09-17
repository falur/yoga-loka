<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken\RegisterNotificationDeviceTokenCommand;
use App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken\RegisterNotificationDeviceTokenHandler;
use App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken\RemoveNotificationDeviceTokenCommand;
use App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken\RemoveNotificationDeviceTokenHandler;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\DeviceToken\RegisterNotificationDeviceTokenFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\DeviceToken\RemoveNotificationDeviceTokenFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Resource\NotificationDeviceTokenResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use Spiral\Router\Annotation\Route;

final class NotificationDeviceTokenController
{
    /**
     * @return DataResponse<NotificationDeviceTokenResource>
     */
    #[Route(route: '/api/v1/notification-device-tokens', name: 'api.v1.notification_device_tokens.register', methods: ['POST'], group: 'api')]
    #[AuthenticatedRoute]
    public function register(
        RegisterNotificationDeviceTokenFilter $registerNotificationDeviceTokenFilter,
        RegisterNotificationDeviceTokenHandler $registerNotificationDeviceTokenHandler,
        CommandBusInterface $commandBus,
    ): DataResponse {
        $deviceToken = $commandBus->dispatch(
            command: new RegisterNotificationDeviceTokenCommand(
                userId: $registerNotificationDeviceTokenFilter->authUserId,
                token: $registerNotificationDeviceTokenFilter->token,
                platform: $registerNotificationDeviceTokenFilter->platform,
            ),
            handler: $registerNotificationDeviceTokenHandler->handle(...),
        );

        return new DataResponse(NotificationDeviceTokenResource::fromEntity($deviceToken));
    }

    /**
     * @return DataResponse<NotificationDeviceTokenResource>
     */
    #[Route(route: '/api/v1/notification-device-tokens', name: 'api.v1.notification_device_tokens.remove', methods: ['DELETE'], group: 'api')]
    #[AuthenticatedRoute]
    public function remove(
        RemoveNotificationDeviceTokenFilter $removeNotificationDeviceTokenFilter,
        RemoveNotificationDeviceTokenHandler $removeNotificationDeviceTokenHandler,
        CommandBusInterface $commandBus,
    ): DataResponse {
        $deviceToken = $commandBus->dispatch(
            command: new RemoveNotificationDeviceTokenCommand(
                userId: $removeNotificationDeviceTokenFilter->authUserId,
                token: $removeNotificationDeviceTokenFilter->token,
            ),
            handler: $removeNotificationDeviceTokenHandler->handle(...),
        );

        return new DataResponse(NotificationDeviceTokenResource::fromEntity($deviceToken));
    }
}
