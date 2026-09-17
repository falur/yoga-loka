<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\NotificationSettingUpdate;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\UpdateNotificationSettingsCommand;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\UpdateNotificationSettingsHandler;
use App\Modules\Notifications\Application\Result\NotificationSettingResult;
use App\Modules\Notifications\Application\Result\NotificationSettingResultCollection;
use App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings\GetNotificationSettingsHandler;
use App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings\GetNotificationSettingsQuery;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\NotificationRecipientFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Setting\NotificationSettingUpdateInput;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Setting\UpdateNotificationSettingsFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Resource\NotificationSettingResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use Spiral\Router\Annotation\Route;

final class NotificationSettingController
{
    /**
     * @return CollectionResponse<NotificationSettingResource>
     */
    #[Route(route: '/api/v1/notification-settings', name: 'api.v1.notification_settings.get', methods: ['GET'], group: 'api')]
    #[AuthenticatedRoute]
    public function get(
        NotificationRecipientFilter $notificationRecipientFilter,
        GetNotificationSettingsHandler $getNotificationSettingsHandler,
        QueryBusInterface $queryBus,
    ): CollectionResponse {
        $settings = $queryBus->dispatch(
            query: new GetNotificationSettingsQuery(userId: $notificationRecipientFilter->authUserId),
            handler: $getNotificationSettingsHandler->handle(...),
        );

        return new CollectionResponse($this->resources($settings));
    }

    /**
     * @return CollectionResponse<NotificationSettingResource>
     */
    #[Route(route: '/api/v1/notification-settings', name: 'api.v1.notification_settings.update', methods: ['PUT'], group: 'api')]
    #[AuthenticatedRoute]
    public function update(
        UpdateNotificationSettingsFilter $updateNotificationSettingsFilter,
        UpdateNotificationSettingsHandler $updateNotificationSettingsHandler,
        CommandBusInterface $commandBus,
    ): CollectionResponse {
        $settings = $commandBus->dispatch(
            command: new UpdateNotificationSettingsCommand(
                userId: $updateNotificationSettingsFilter->authUserId,
                updates: $this->updates($updateNotificationSettingsFilter),
            ),
            handler: $updateNotificationSettingsHandler->handle(...),
        );

        return new CollectionResponse($this->resources($settings));
    }

    /**
     * @return list<NotificationSettingUpdate>
     */
    private function updates(UpdateNotificationSettingsFilter $filter): array
    {
        return \array_map(
            callback: static fn(NotificationSettingUpdateInput $input): NotificationSettingUpdate => new NotificationSettingUpdate(
                type: $input->type,
                channel: $input->channel,
                enabled: $input->enabled,
            ),
            array: $filter->settings,
        );
    }

    /**
     * @return list<NotificationSettingResource>
     */
    private function resources(NotificationSettingResultCollection $settings): array
    {
        return $settings->mapToList(
            static fn(NotificationSettingResult $setting): NotificationSettingResource => NotificationSettingResource::fromResult($setting),
        );
    }
}
