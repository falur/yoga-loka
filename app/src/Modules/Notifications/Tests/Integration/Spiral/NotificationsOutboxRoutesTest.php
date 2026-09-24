<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use Tests\Support\Outbox\AssertsOutboxRoutes;
use Tests\TestCase;

/**
 * Три маршрута модуля: рассылка, push и realtime. У realtime список пауз пуст — единственная
 * попытка без повтора.
 */
final class NotificationsOutboxRoutesTest extends TestCase
{
    use AssertsOutboxRoutes;

    public function testNotificationRequestedGoesToNotificationsQueue(): void
    {
        $this->assertOutboxRoute(
            eventClass: NotificationRequestedEvent::class,
            jobClass: DispatchNotificationJob::class,
            queueName: QueueName::Notifications,
            retryDelaysSeconds: [60, 300],
            deliveryTimeoutSeconds: 600,
        );
    }

    public function testNotificationPushRequestedGoesToNotificationsQueue(): void
    {
        $this->assertOutboxRoute(
            eventClass: NotificationPushRequestedEvent::class,
            jobClass: SendPushNotificationJob::class,
            queueName: QueueName::Notifications,
            retryDelaysSeconds: [60, 300],
            deliveryTimeoutSeconds: 600,
        );
    }

    public function testNotificationRealtimeRequestedHasSingleAttempt(): void
    {
        $this->assertOutboxRoute(
            eventClass: NotificationRealtimeRequestedEvent::class,
            jobClass: PublishRealtimeNotificationJob::class,
            queueName: QueueName::Notifications,
            retryDelaysSeconds: [],
            deliveryTimeoutSeconds: 120,
        );
    }
}
