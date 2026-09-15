<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Controller;

use App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead\MarkAllNotificationsReadCommand;
use App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead\MarkAllNotificationsReadHandler;
use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadCommand;
use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadHandler;
use App\Modules\Notifications\Application\Query\Notification\GetUnreadCount\GetUnreadCountHandler;
use App\Modules\Notifications\Application\Query\Notification\GetUnreadCount\GetUnreadCountQuery;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsHandler;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsQuery;
use App\Modules\Notifications\Application\View\NotificationView;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Notification\ListNotificationsFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Notification\MarkNotificationReadFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\NotificationRecipientFilter;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Resource\NotificationResource;
use App\Modules\Notifications\Infrastructure\Spiral\Http\Resource\UnreadCountResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Router\Annotation\Route;

final class NotificationController
{
    /**
     * @return PaginationResponse<NotificationResource>
     */
    #[Route(route: '/api/v1/notifications', name: 'api.v1.notifications.list', methods: ['GET'], group: 'api')]
    public function list(
        ListNotificationsFilter $listNotificationsFilter,
        ListNotificationsHandler $listNotificationsHandler,
        QueryBusInterface $queryBus,
    ): PaginationResponse {
        $result = $queryBus->dispatch(
            query: new ListNotificationsQuery(
                userId: $listNotificationsFilter->authUserId,
                cursor: $listNotificationsFilter->cursor,
                limit: $listNotificationsFilter->limit,
            ),
            handler: $listNotificationsHandler->handle(...),
        );

        $resources = $result->notifications->mapToList(
            static fn(NotificationView $notification): NotificationResource => NotificationResource::fromView($notification),
        );

        return new PaginationResponse(
            data: $resources,
            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $listNotificationsFilter->limit),
        );
    }

    /**
     * @return DataResponse<UnreadCountResource>
     */
    #[Route(route: '/api/v1/notifications/unread-count', name: 'api.v1.notifications.unread_count', methods: ['GET'], group: 'api')]
    public function unreadCount(
        NotificationRecipientFilter $notificationRecipientFilter,
        GetUnreadCountHandler $getUnreadCountHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $result = $queryBus->dispatch(
            query: new GetUnreadCountQuery(userId: $notificationRecipientFilter->authUserId),
            handler: $getUnreadCountHandler->handle(...),
        );

        return new DataResponse(new UnreadCountResource(count: $result->count));
    }

    /**
     * @return DataResponse<NotificationResource>
     */
    #[Route(route: '/api/v1/notifications/<id>/read', name: 'api.v1.notifications.read', methods: ['POST'], group: 'api')]
    public function read(
        string $id,
        MarkNotificationReadFilter $markNotificationReadFilter,
        MarkNotificationReadHandler $markNotificationReadHandler,
        CommandBusInterface $commandBus,
    ): DataResponse {
        $notification = $commandBus->dispatch(
            command: new MarkNotificationReadCommand(
                userId: $markNotificationReadFilter->authUserId,
                notificationId: $markNotificationReadFilter->id,
            ),
            handler: $markNotificationReadHandler->handle(...),
        );

        return new DataResponse(NotificationResource::fromView($notification));
    }

    /**
     * @return DataResponse<UnreadCountResource>
     */
    #[Route(route: '/api/v1/notifications/read-all', name: 'api.v1.notifications.read_all', methods: ['POST'], group: 'api')]
    public function readAll(
        NotificationRecipientFilter $notificationRecipientFilter,
        MarkAllNotificationsReadHandler $markAllNotificationsReadHandler,
        CommandBusInterface $commandBus,
    ): DataResponse {
        $commandBus->dispatch(
            command: new MarkAllNotificationsReadCommand(userId: $notificationRecipientFilter->authUserId),
            handler: $markAllNotificationsReadHandler->handle(...),
        );

        // Счётчик договорный: сразу после mark-all все уведомления прочитаны, поэтому возвращаем 0
        // без повторного запроса. Узкая гонка с доставкой нового уведомления допустима для MVP —
        // клиент актуализирует счётчик ближайшим unread-count.
        return new DataResponse(new UnreadCountResource(count: 0));
    }
}
